<?php

declare(strict_types=1);

namespace App\Services\Agents\Tools;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Tell a person something needs attention.
 *
 * The escape hatch every agent needs. An agent that finds a broken scraper, a cost anomaly
 * or a corpus gap and has no way to say so is an agent whose findings die in a trace
 * nobody reads.
 *
 * Deliberately writes to the log rather than sending a notification itself. Alert routing,
 * deduplication and the on-call rota are operational concerns, and an agent should not be
 * able to wake somebody at 3 AM on its own judgement.
 */
final class RaiseAlertTool implements Tool
{
    public function key(): string
    {
        return 'raise_alert';
    }

    public function description(): string
    {
        return 'Flag something a person should look at: a broken scraper, a cost anomaly, a '
            .'gap in our material. Use severity honestly — marking everything urgent is how '
            .'alerts stop being read at all.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'severity' => ['type' => 'string', 'enum' => ['info', 'warning', 'urgent']],
                'title' => ['type' => 'string'],
                'detail' => ['type' => 'string'],
                'suggested_action' => ['type' => 'string'],
            ],
            'required' => ['severity', 'title'],
        ];
    }

    public function writesOwnerTable(): bool
    {
        return false;
    }

    public function isDestructive(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(array $input, AgentContext $context): ToolResult
    {
        $context = [
            'agent' => $context->definition->key,
            'run' => $context->run->uuid,
            'title' => $input['title'],
            'detail' => $input['detail'] ?? null,
            'action' => $input['suggested_action'] ?? null,
        ];

        match ($input['severity']) {
            'urgent' => Log::error('agent.alert', $context),
            'warning' => Log::warning('agent.alert', $context),
            default => Log::info('agent.alert', $context),
        };

        return ToolResult::success(
            ['raised' => true],
            'Alert recorded. A person will see it — do not raise the same one again in this run.',
        );
    }
}
