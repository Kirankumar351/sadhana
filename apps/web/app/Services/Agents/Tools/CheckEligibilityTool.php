<?php

declare(strict_types=1);

namespace App\Services\Agents\Tools;

use App\Models\ExamNotification;
use App\Services\Agents\AgentContext;
use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\ToolResult;
use App\Services\EligibilityService;

/**
 * Ask the deterministic engine whether someone is eligible.
 *
 * THIS IS THE ONLY WAY AN AGENT MAY TOUCH ELIGIBILITY, and it is why the tool exists at
 * all. Without it, an agent asked "am I eligible" would reason its way to an answer from
 * retrieved criteria — fluently, and sometimes wrongly, about whether a person should
 * spend a year of their life on an exam.
 *
 * The tool returns the engine's verdict verbatim. The agent's job is to report it, never
 * to interpret it.
 */
final class CheckEligibilityTool implements Tool
{
    public function __construct(
        private readonly EligibilityService $eligibility,
    ) {}

    public function key(): string
    {
        return 'check_eligibility';
    }

    public function description(): string
    {
        return 'Check whether the current user is eligible for a notification. You MUST use '
            .'this rather than working it out yourself from age limits and qualifications — '
            .'the calculation involves category relaxations and an "as on" reference date '
            .'that is not the application deadline. Report exactly what this returns and do '
            .'not add your own judgement to it.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'notification_slug' => ['type' => 'string'],
            ],
            'required' => ['notification_slug'],
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
        // Only a student agent has a user to check, and only ever its own.
        if ($context->user === null) {
            return ToolResult::failure('No user in context — eligibility cannot be checked.');
        }

        $notification = ExamNotification::query()
            ->where('slug', $input['notification_slug'])
            ->where('status', 'published')
            ->first();

        if ($notification === null) {
            return ToolResult::failure('No published notification with that slug.');
        }

        $verdict = $this->eligibility->check($notification, $context->user->profile);

        return ToolResult::success([
            'status' => $verdict['status'],
            'matched' => array_column($verdict['matched'], 'label'),
            'failed' => array_map(
                static fn (array $failure): string => $failure['label'].': '.$failure['reason'],
                $verdict['failed'],
            ),
            'caveat' => $verdict['caveat'],
        ], 'Report this verdict as given. Always include the caveat.');
    }
}
