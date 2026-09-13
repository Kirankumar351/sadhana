<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A structured extraction, with the usage it cost.
 *
 * The usage figures are the reason this is an object rather than a bare array. Answer
 * evaluation and mock interview are the two most expensive calls in the product — a mains
 * answer plus a rubric plus a model answer is a large prompt, and an interview is twenty of
 * them in a row. Returning only the decoded JSON left those calls invisible to CostMeter,
 * so the one line item most likely to break the AI budget was the one line item nobody
 * could see.
 *
 * @template TData of array<string, mixed>
 */
final readonly class StructuredResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $model = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->data === [];
    }

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
