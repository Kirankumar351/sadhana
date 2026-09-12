<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\AgentDefinition;
use App\Models\AgentRun;
use App\Models\AgentStep;
use App\Models\User;
use App\Services\Agents\Contracts\Tool;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The agent loop: think, call a tool, observe, repeat, answer.
 *
 * WHAT MAKES THIS DIFFERENT FROM AiGateway. A gateway call answers one question and
 * returns. An agent has a standing goal, chooses its own tools, runs several steps, and
 * remembers between runs. That is why it needs a trace rather than a log line — an agent
 * nobody can read the reasoning of is an agent nobody will leave running.
 *
 * THE SAFETY RULE IS UNCHANGED. An agent drafts; a human decides. There is no autonomy
 * level in this system that publishes. The highest is `act`, which covers internal state —
 * reindexing a chunk, retrying a scraper, tagging a sales lead. Anything a student will
 * read goes to `ai_drafts` and waits for a person. There is deliberately no
 * publish_notification tool, no approve_question tool and no set_eligibility tool for an
 * agent to reach for.
 *
 * THREE CEILINGS, ENFORCED HERE AND NOT NEGOTIABLE BY A DEFINITION:
 *   - step count. A loop with a broken exit condition is the most expensive failure mode
 *     in an AI product.
 *   - spend per run, checked after every step rather than at the end, because checking at
 *     the end means the money is already gone.
 *   - wall clock.
 *
 * Every halt is recorded with its reason. An agent that quietly stopped is indistinguishable
 * from one that finished, and that ambiguity is how a broken pipeline runs unnoticed for a
 * week.
 */
final class AgentRuntime
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly AgentModelClient $client,
        private readonly AgentMemory $memory,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(
        AgentDefinition $definition,
        array $input = [],
        ?User $user = null,
        string $trigger = 'manual',
        ?string $triggerRef = null,
    ): AgentRun {
        $run = AgentRun::create([
            'agent_definition_id' => $definition->id,
            'user_id' => $user?->id,
            'trigger' => $trigger,
            'trigger_ref' => $triggerRef,
            'input' => $input,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startedAt = microtime(true);

        try {
            // Binding happens before the first model call, so a misconfigured agent fails
            // immediately and for free rather than after spending three steps.
            $tools = $this->registry->for($definition);

            $context = new AgentContext(
                run: $run,
                definition: $definition,
                user: $user,
                memories: $this->memory->recall($definition, $user),
            );

            $this->loop($run, $definition, $tools, $context, $input, $startedAt);

            if ($run->status === 'running') {
                $run->update(['status' => 'succeeded']);
            }
        } catch (Throwable $e) {
            report($e);

            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        } finally {
            $run->update([
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        }

        return $run->fresh();
    }

    /**
     * @param  array<string, Tool>  $tools
     * @param  array<string, mixed>  $input
     */
    private function loop(
        AgentRun $run,
        AgentDefinition $definition,
        array $tools,
        AgentContext $context,
        array $input,
        float $startedAt,
    ): void {
        $maxSteps = min(
            $definition->max_steps,
            (int) config('agents.limits.max_steps_hard'),
        );

        $costCeiling = min(
            $definition->max_cost_paise_per_run,
            (int) config('agents.limits.max_cost_paise_per_run'),
        );

        $timeout = min(
            $definition->timeout_sec,
            (int) config('agents.limits.timeout_sec'),
        );

        $transcript = [];
        $spent = 0;

        for ($step = 0; $step < $maxSteps; $step++) {
            if (microtime(true) - $startedAt > $timeout) {
                $this->halt($run, 'timeout');

                return;
            }

            $decision = $this->client->next(
                definition: $definition,
                tools: $this->registry->toSchema($tools),
                transcript: $transcript,
                input: $input,
                context: $context,
            );

            $spent += $decision->costPaise;

            $this->recordStep($run, $step, $decision);

            // Checked after every step, not at the end. Checking at the end means the
            // money is already spent.
            if ($spent > $costCeiling) {
                $this->halt($run, 'budget');

                return;
            }

            if ($decision->isFinal()) {
                $run->update([
                    'output' => $decision->output,
                    'steps_used' => $step + 1,
                    'cost_paise' => $spent,
                ]);

                $this->memory->remember($definition, $context->user, $decision->memories);

                return;
            }

            $tool = $tools[$decision->toolKey] ?? null;

            if ($tool === null) {
                // The model asked for something it was not given. Feed the refusal back
                // rather than failing the run — it usually recovers on the next step.
                $transcript[] = [
                    'role' => 'tool_error',
                    'content' => "Tool '{$decision->toolKey}' is not available to you.",
                ];

                continue;
            }

            $result = $this->execute($tool, $decision->toolInput, $context);

            if ($result->createdDraft) {
                $run->increment('drafts_created');
            }

            $transcript[] = [
                'role' => 'observation',
                'tool' => $decision->toolKey,
                'content' => $result->toPromptString(),
            ];
        }

        // Out of steps without a final answer. Recorded as halted, not succeeded, because
        // the two mean very different things when something downstream is waiting.
        $this->halt($run, 'max_steps');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function execute(Tool $tool, array $input, AgentContext $context): ToolResult
    {
        try {
            return $tool->run($input, $context);
        } catch (Throwable $e) {
            report($e);

            // A tool failure is information for the agent, not the end of the run.
            return ToolResult::failure($e->getMessage());
        }
    }

    private function recordStep(AgentRun $run, int $index, AgentDecision $decision): void
    {
        DB::transaction(static function () use ($run, $index, $decision): void {
            AgentStep::create([
                'agent_run_id' => $run->id,
                'step_index' => $index,
                'type' => $decision->isFinal() ? 'answer' : 'tool_call',
                'tool_key' => $decision->toolKey,
                'tool_input' => $decision->toolInput,
                'content' => $decision->reasoning,
                'input_tokens' => $decision->inputTokens,
                'output_tokens' => $decision->outputTokens,
                'cost_paise' => $decision->costPaise,
            ]);
        });
    }

    private function halt(AgentRun $run, string $reason): void
    {
        $run->update([
            'status' => 'halted',
            'halt_reason' => $reason,
        ]);
    }
}
