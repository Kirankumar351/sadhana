<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A raw completion, before any guard has looked at it.
 *
 * Nothing outside AiGateway should ever hold one of these — the whole point of the gateway
 * is that unguarded model output never reaches a caller.
 */
final readonly class ModelResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public float $confidence = 1.0,
        public ?string $stopReason = null,
    ) {}

    /**
     * Cost in integer paise. Money is never a float anywhere in this codebase.
     *
     * @param  array{input: float, output: float}  $ratePerMillionPaise
     */
    public function costPaise(array $ratePerMillionPaise): int
    {
        $input = $this->inputTokens / 1_000_000 * $ratePerMillionPaise['input'];
        $output = $this->outputTokens / 1_000_000 * $ratePerMillionPaise['output'];

        return (int) ceil($input + $output);
    }
}
