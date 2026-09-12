<?php

declare(strict_types=1);

namespace App\Services\Agents;

/**
 * One decision from the model inside the agent loop: either call a tool, or finish.
 *
 * `reasoning` is stored on every step. It is the difference between an agent you can debug
 * and one you can only restart — when a news curator holds an item nobody expected it to
 * hold, the reasoning is the only artefact that explains why.
 */
final readonly class AgentDecision
{
    /**
     * @param  array<string, mixed>  $toolInput
     * @param  array<string, mixed>  $output
     * @param  array<string, string>  $memories
     */
    private function __construct(
        public ?string $toolKey,
        public array $toolInput,
        public ?string $reasoning,
        public array $output,
        public array $memories,
        public int $inputTokens,
        public int $outputTokens,
        public int $costPaise,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function callTool(
        string $toolKey,
        array $input,
        ?string $reasoning,
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $costPaise = 0,
    ): self {
        return new self(
            toolKey: $toolKey,
            toolInput: $input,
            reasoning: $reasoning,
            output: [],
            memories: [],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costPaise: $costPaise,
        );
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, string>  $memories  facts worth carrying to the next run
     */
    public static function finish(
        array $output,
        ?string $reasoning = null,
        array $memories = [],
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $costPaise = 0,
    ): self {
        return new self(
            toolKey: null,
            toolInput: [],
            reasoning: $reasoning,
            output: $output,
            memories: $memories,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costPaise: $costPaise,
        );
    }

    public function isFinal(): bool
    {
        return $this->toolKey === null;
    }
}
