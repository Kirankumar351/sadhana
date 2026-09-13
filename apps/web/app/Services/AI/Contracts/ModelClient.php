<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Services\Agents\AgentDecision;
use App\Services\AI\ModelResponse;
use App\Services\AI\ResolvedPrompt;
use App\Services\AI\RetrievedPassage;
use App\Services\AI\StructuredResponse;

/**
 * The provider boundary.
 *
 * Only AiGateway may depend on an implementation of this. An architecture test fails the
 * build if any class outside App\Services\AI references a provider SDK directly — because
 * the moment a feature can call the API itself, every cost cap, refusal log and output
 * filter in the gateway becomes optional.
 */
interface ModelClient
{
    /**
     * @param  list<RetrievedPassage>  $passages
     * @param  'small'|'large'  $tier
     */
    public function complete(
        ResolvedPrompt $prompt,
        array $passages,
        string $question,
        string $tier = 'large',
    ): ModelResponse;

    /**
     * Structured extraction — used by the notification extractor and the news pipeline.
     *
     * The instruction always includes "never guess a date". That phrase is load-bearing:
     * a hallucinated deadline is the single worst failure this product can produce, and
     * everything the extractor returns goes to human review regardless.
     *
     * Returns the usage alongside the data, because the two features that lean on this —
     * answer evaluation and mock interview — are the most expensive calls in the product
     * and were invisible to CostMeter while this returned a bare array.
     *
     * @param  array<string, mixed>  $schema  JSON Schema the output must satisfy
     */
    public function extract(string $instruction, string $content, array $schema, string $tier = 'large'): StructuredResponse;

    /**
     * One step of an agent loop: given a goal, the available tools and the transcript so
     * far, either call a tool or finish.
     *
     * Separate from `complete()` because an agent step is multi-turn, tool-using and
     * uncached, where a grounded answer is none of those.
     *
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array<string, mixed>>  $transcript
     * @param  array<string, mixed>  $input
     * @param  'small'|'large'  $tier
     */
    public function decide(
        string $system,
        array $tools,
        array $transcript,
        array $input,
        string $tier = 'large',
    ): AgentDecision;
}
