<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\ToolRegistry;
use App\Services\Agents\Tools\CheckEligibilityTool;
use App\Services\Agents\Tools\DraftQuestionTool;
use App\Services\Agents\Tools\RaiseAlertTool;
use App\Services\Agents\Tools\SearchCorpusTool;
use Illuminate\Support\ServiceProvider;

/**
 * The tool registry.
 *
 * A TOOL THAT IS NOT REGISTERED HERE CANNOT BE CALLED BY ANY AGENT. That is the point: the
 * registry is a hard allowlist, not a convenience, and adding to it should feel like a
 * decision rather than an import.
 *
 * Every entry must also appear in config('agents.tools') with its safety properties
 * declared. ToolRegistry refuses to bind anything missing from that list, so a tool added
 * here and forgotten there fails loudly at bind time rather than running unreviewed.
 */
class AgentServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<Tool>>
     */
    private const TOOLS = [
        // Read-only. Safe for every agent family.
        'search_corpus' => SearchCorpusTool::class,

        // Routes to the deterministic engine. The ONLY way an agent may touch eligibility.
        'check_eligibility' => CheckEligibilityTool::class,

        // Proposes into ai_drafts and stops. Requires human approval to reach a student.
        'draft_question' => DraftQuestionTool::class,

        // The escape hatch. Writes to the log, never sends a notification itself.
        'raise_alert' => RaiseAlertTool::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app): ToolRegistry {
            $registry = new ToolRegistry($app);

            foreach (self::TOOLS as $key => $class) {
                $registry->register($key, $class);
            }

            return $registry;
        });
    }
}
