<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\AgentDefinition;
use App\Services\AI\Contracts\ModelClient;

/**
 * Turns the agent's goal, tools and transcript into one model decision.
 *
 * Kept separate from AiGateway on purpose. The gateway is built for student-facing,
 * retrieval-grounded, cached single answers; an agent step is none of those — it is
 * multi-turn, tool-using and uncached. Forcing both through one class would mean each
 * carrying branches for the other's requirements, and the guarantees the gateway exists to
 * enforce would get weaker as a result.
 *
 * They share the cost ceiling and the provider boundary, which is what actually matters.
 */
final class AgentModelClient
{
    public function __construct(
        private readonly ModelClient $client,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array<string, mixed>>  $transcript
     * @param  array<string, mixed>  $input
     */
    public function next(
        AgentDefinition $definition,
        array $tools,
        array $transcript,
        array $input,
        AgentContext $context,
    ): AgentDecision {
        $system = $this->systemPrompt($definition, $context);

        // Provider wiring lives in the concrete ModelClient. This method's job is to build
        // an honest prompt and to translate the reply into an AgentDecision — nothing else.
        return $this->client->decide(
            system: $system,
            tools: $tools,
            transcript: $transcript,
            input: $input,
            tier: $definition->model_tier,
        );
    }

    private function systemPrompt(AgentDefinition $definition, AgentContext $context): string
    {
        $memories = $context->memories === []
            ? '(nothing remembered yet)'
            : collect($context->memories)
                ->map(static fn (string $v, string $k): string => "- {$k}: {$v}")
                ->implode("\n");

        $autonomyRule = match ($definition->autonomy) {
            'act' => 'You may use your tools to change internal state. You may NOT publish '
                .'anything a student will read.',
            'escalate' => 'You may raise an alert for a person to act on. You may not act yourself.',
            default => 'You may only PROPOSE. Everything you produce goes to a human review '
                .'queue. You cannot publish.',
        };

        return <<<PROMPT
            {$definition->goal}

            HOW YOU OPERATE
            {$autonomyRule}

            You are one part of a Telugu-first portal for Indian government job aspirants.
            The people who read what you produce are deciding how to spend a year of their
            lives, so being wrong is expensive in a way that being slow is not.

            RULES THAT DO NOT BEND
            - Never state whether a person is eligible for anything. Call the eligibility
              tool and report exactly what it returns.
            - Never give a date, a fee or a deadline that is not present in something you
              retrieved. If you did not read it, you do not know it.
            - Never predict a cutoff, a rank or a selection chance. Show real history instead.
            - Never promise or imply selection.
            - Work only from our own indexed material. Never from memory of the open web.
            - If you cannot do the task with the tools you have, say so and stop. Do not
              improvise around a missing tool.

            WHAT YOU REMEMBER
            {$memories}

            Write output in {$context->locale()}. Keep numerals in Latin script.
            Think briefly before each tool call; say what you are trying to establish.
            PROMPT;
    }
}
