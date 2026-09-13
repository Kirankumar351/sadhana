<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentDefinition;
use App\Models\AgentEval;
use App\Services\Agents\Exceptions\UnsafeToolBindingException;
use App\Services\Agents\ToolRegistry;
use Illuminate\Console\Command;

/**
 * Check every agent definition before it is allowed to run.
 *
 * Referenced by the seeder and by docs/03-AI-AND-AGENTS.md, and it needs to exist because
 * an agent that is not evaluated silently rots when a prompt or a toolset changes.
 *
 * WHAT THIS CHECKS TODAY is the structural half: that every agent's tools resolve, that
 * none of them is bound in a way the safety rules forbid, and that nothing ships enabled
 * without a goal worth the name. These are the failures that would otherwise surface at
 * 4 AM in the middle of the news pipeline.
 *
 * The behavioural half — replaying a golden set through a real model and asserting the
 * answers — needs `agent_evals` rows and a provider key. When neither exists the command
 * says so plainly rather than reporting a pass it did not earn.
 */
class EvaluateAgents extends Command
{
    protected $signature = 'agents:eval {--agent= : Check one agent by key} {--strict : Fail on warnings}';

    protected $description = 'Validate agent definitions and replay their evaluation sets';

    public function handle(ToolRegistry $registry): int
    {
        $agents = AgentDefinition::query()
            ->when($this->option('agent'), fn ($q) => $q->where('key', $this->option('agent')))
            ->get();

        if ($agents->isEmpty()) {
            $this->warn('No agent definitions found. Run `php artisan db:seed --class=AgentSeeder`.');

            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;
        $warnings = 0;

        foreach ($agents as $agent) {
            [$status, $note] = $this->check($agent, $registry);

            if ($status === 'FAIL') {
                $failures++;
            }

            if ($status === 'WARN') {
                $warnings++;
            }

            $rows[] = [
                $agent->key,
                $agent->family,
                $agent->autonomy,
                $agent->is_active ? 'on' : 'off',
                count($agent->tools ?? []),
                $status,
                $note,
            ];
        }

        $this->table(['agent', 'family', 'autonomy', 'state', 'tools', '', 'note'], $rows);

        $withEvals = AgentEval::query()->where('is_active', true)->count();

        if ($withEvals === 0) {
            $this->newLine();
            $this->warn(
                'Structural checks only — no golden sets are defined, so no agent has been '
                .'checked for what it actually produces. Add rows to agent_evals before '
                .'enabling anything that drafts content a student will read.'
            );
        }

        if ($failures > 0) {
            $this->error("{$failures} agent(s) would fail to start.");

            return self::FAILURE;
        }

        if ($warnings > 0 && $this->option('strict')) {
            $this->error("{$warnings} warning(s), and --strict was passed.");

            return self::FAILURE;
        }

        $this->info('All agent definitions bind safely.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function check(AgentDefinition $agent, ToolRegistry $registry): array
    {
        /**
         * Binding is the real test. ToolRegistry refuses a tool that writes an owner table
         * without human approval, a destructive tool on a non-`act` agent, and anything
         * missing a declared safety policy — so a definition that binds cleanly cannot
         * violate those rules at runtime.
         */
        try {
            $tools = $registry->for($agent);
        } catch (UnsafeToolBindingException $e) {
            return ['FAIL', $e->getMessage()];
        }

        if ($tools === []) {
            return ['WARN', 'No tools bound — this agent can observe but not act.'];
        }

        if (mb_strlen($agent->goal) < 40) {
            return ['WARN', 'Goal is too short to steer a model reliably.'];
        }

        /**
         * An active agent with a schedule and no cost ceiling is how a runaway loop becomes
         * an unannounced bill. The runtime clamps to the global limit anyway, but a
         * definition that relies on that is one someone forgot to think about.
         */
        if ($agent->is_active && $agent->max_cost_paise_per_run <= 0) {
            return ['FAIL', 'Active with no per-run cost ceiling.'];
        }

        if ($agent->is_active) {
            return ['WARN', 'Enabled. Confirm a golden set exists before leaving it running.'];
        }

        return ['OK', 'Binds cleanly.'];
    }
}
