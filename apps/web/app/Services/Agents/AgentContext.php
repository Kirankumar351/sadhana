<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\User;

/**
 * Everything a tool is allowed to know about the run it is executing inside.
 *
 * Passed to every tool so that none of them needs to reach for global state. The important
 * property is what is NOT here: no request, no session, no container. A tool that can only
 * see this object is a tool that behaves the same whether it was triggered by a schedule,
 * an event or a person clicking a button in admin.
 *
 * `user` is null for every ops, business and platform agent. It is set only for student
 * agents, where it scopes both memory and every read.
 */
final readonly class AgentContext
{
    /**
     * @param  array<string, string>  $memories
     */
    public function __construct(
        public AgentRun $run,
        public AgentDefinition $definition,
        public ?User $user = null,
        public array $memories = [],
    ) {}

    /**
     * The locale this run should produce output in.
     *
     * A student agent follows the student. An ops agent drafting content follows the
     * platform default, which is Telugu — this is a Telugu-first product, and content
     * drafted in English by default would quietly invert that.
     */
    public function locale(): string
    {
        return $this->user?->preferred_locale
            ?? (string) config('locales.default', 'te');
    }

    public function isStudentAgent(): bool
    {
        return $this->definition->family === 'student';
    }

    /**
     * Guard for tools that read personal data. A student agent may only ever read the
     * user it was launched for; there is no code path that lets it read another.
     */
    public function assertOwns(int $userId): void
    {
        if ($this->user === null || $this->user->id !== $userId) {
            throw new \RuntimeException(
                'Agent attempted to read data belonging to a different user.'
            );
        }
    }
}
