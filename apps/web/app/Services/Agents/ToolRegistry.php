<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\AgentDefinition;
use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\Exceptions\UnsafeToolBindingException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the tools an agent is allowed to use, and refuses unsafe bindings.
 *
 * A HARD ALLOWLIST, NOT A FILTER. An agent sees only the tools named in its definition.
 * There is no "all tools" mode and no wildcard, because the failure it would prevent —
 * an agent reaching for a capability nobody intended it to have — is exactly the failure
 * that is hardest to notice until it has already happened.
 *
 * Three checks run at bind time, before any model is called:
 *
 *   1. The tool must exist in the registry.
 *   2. A tool that writes an owner table may only be bound to a step gated behind human
 *      approval. Eligibility criteria, dates, fees and answer keys are not agent-writable.
 *   3. A destructive tool may only be bound to an agent whose autonomy is `act`, and never
 *      to a student-facing agent.
 *
 * Binding failures throw. They are configuration errors, caught in CI by the agent
 * definition test, not conditions to be handled at runtime.
 */
final class ToolRegistry
{
    /** @var array<string, class-string<Tool>> */
    private array $tools = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<Tool>  $class
     */
    public function register(string $key, string $class): void
    {
        $this->tools[$key] = $class;
    }

    /**
     * Resolve every tool an agent definition allows.
     *
     * @return array<string, Tool>
     *
     * @throws UnsafeToolBindingException
     */
    public function for(AgentDefinition $definition): array
    {
        $resolved = [];

        /** @var list<string> $allowed */
        $allowed = $definition->tools ?? [];

        foreach ($allowed as $key) {
            $tool = $this->resolve($key);

            $this->assertSafeBinding($tool, $definition);

            $resolved[$key] = $tool;
        }

        return $resolved;
    }

    public function resolve(string $key): Tool
    {
        if (! isset($this->tools[$key])) {
            throw new UnsafeToolBindingException(
                "Unknown tool '{$key}'. Register it in AgentServiceProvider before an agent "
                .'definition can reference it.'
            );
        }

        /** @var Tool */
        return $this->container->make($this->tools[$key]);
    }

    /**
     * @throws UnsafeToolBindingException
     */
    private function assertSafeBinding(Tool $tool, AgentDefinition $definition): void
    {
        /** @var array<string, array{writes_owner: bool, approval: bool}> $policy */
        $policy = config('agents.tools', []);

        $declared = $policy[$tool->key()] ?? null;

        if ($declared === null) {
            throw new UnsafeToolBindingException(
                "Tool '{$tool->key()}' has no entry in config('agents.tools'). Every tool must "
                .'be declared there so its safety properties are reviewable in one place.'
            );
        }

        // A tool that writes an owner table can only ever sit behind human approval.
        // This is the rule the whole AI layer rests on, expressed as code.
        if ($tool->writesOwnerTable() && ! $declared['approval']) {
            throw new UnsafeToolBindingException(
                "Tool '{$tool->key()}' writes an owner table but is not marked as requiring "
                ."approval. Eligibility criteria, dates, fees and answer keys are never "
                .'agent-writable. Make it draft into ai_drafts instead.'
            );
        }

        if ($tool->isDestructive() && $definition->autonomy !== 'act') {
            throw new UnsafeToolBindingException(
                "Destructive tool '{$tool->key()}' cannot be bound to agent "
                ."'{$definition->key}' with autonomy '{$definition->autonomy}'."
            );
        }

        // A student-facing agent runs against one person's data on their behalf. It has no
        // business holding a tool that changes shared state for everyone.
        if ($definition->family === 'student' && $tool->isDestructive()) {
            throw new UnsafeToolBindingException(
                "Student agent '{$definition->key}' cannot hold destructive tool '{$tool->key()}'."
            );
        }
    }

    /**
     * Tool definitions in the shape the model expects them.
     *
     * @param  array<string, Tool>  $tools
     * @return list<array<string, mixed>>
     */
    public function toSchema(array $tools): array
    {
        return array_values(array_map(
            static fn (Tool $tool): array => [
                'name' => $tool->key(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ],
            $tools,
        ));
    }
}
