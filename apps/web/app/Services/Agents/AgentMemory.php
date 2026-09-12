<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\AgentDefinition;
use App\Models\AgentMemoryRecord;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * What makes the tutor a tutor rather than a chatbot.
 *
 * Memory is scoped to (agent, user). One student's memory can never enter another's
 * context — there is no query in this class that omits the user scope for a student agent.
 *
 * MEMORIES DECAY ON PURPOSE. A note that someone was weak in Polity in January is actively
 * misleading in June, after they have spent four months fixing it. A tutor that keeps
 * quoting an obsolete weakness is worse than one with no memory at all, because the student
 * trusts it. So observations expire unless something refreshes them, and only durable facts
 * — target exam, home district, graduation subject — are kept indefinitely.
 */
final class AgentMemory
{
    /** Observations are short-lived; facts and preferences are not. */
    private const TTL_DAYS = [
        'observation' => 45,
        'summary' => 90,
        'preference' => 365,
        'fact' => null,
    ];

    /**
     * @return array<string, string>
     */
    public function recall(AgentDefinition $definition, ?User $user, int $limit = 40): array
    {
        $query = AgentMemoryRecord::query()
            ->where('agent_definition_id', $definition->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($definition->family === 'student') {
            // A student agent with no user is a programming error, not an empty result.
            if (! $user instanceof User) {
                throw new \RuntimeException(
                    "Student agent '{$definition->key}' was run without a user."
                );
            }

            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id');
        }

        return $query
            ->orderByRaw("FIELD(kind, 'fact', 'preference', 'summary', 'observation')")
            ->latest('updated_at')
            ->limit($limit)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * @param  array<string, string>  $memories
     */
    public function remember(AgentDefinition $definition, ?User $user, array $memories): void
    {
        foreach ($memories as $key => $value) {
            $kind = $this->classify($key);

            AgentMemoryRecord::updateOrCreate(
                [
                    'agent_definition_id' => $definition->id,
                    'user_id' => $user?->id,
                    'key' => $key,
                ],
                [
                    'value' => $value,
                    'kind' => $kind,
                    'scope' => $user !== null ? 'user' : 'global',
                    'expires_at' => $this->expiryFor($kind),
                ],
            );
        }
    }

    public function forget(AgentDefinition $definition, User $user): int
    {
        return AgentMemoryRecord::query()
            ->where('agent_definition_id', $definition->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Memory is personal data under the DPDP Act, so it is part of both the export and the
     * deletion routine — Integration Map gap 12.
     *
     * @return array<string, mixed>
     */
    public function exportFor(User $user): array
    {
        return AgentMemoryRecord::query()
            ->where('user_id', $user->id)
            ->get(['key', 'value', 'kind', 'created_at'])
            ->toArray();
    }

    private function classify(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'fact.') => 'fact',
            str_starts_with($key, 'pref.') => 'preference',
            str_starts_with($key, 'summary.') => 'summary',
            default => 'observation',
        };
    }

    private function expiryFor(string $kind): ?CarbonInterface
    {
        $days = self::TTL_DAYS[$kind] ?? 45;

        return $days === null ? null : now()->addDays($days);
    }
}
