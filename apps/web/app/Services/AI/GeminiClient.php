<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\Agents\AgentDecision;
use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\Contracts\ReportsConfiguration;

/**
 * Google Gemini behind the same boundary as Claude.
 *
 * It implements ModelClient and nothing more, so every guarantee in AiGateway — intent
 * routing, caps, the retrieval gate, the output guard, metering — applies unchanged. Which
 * client is bound is decided by AiProvider from the keys that exist.
 *
 * Behaviour verified against the live API in September 2026, and the reason for each choice
 * below:
 *
 *   - THINKING COUNTS AGAINST maxOutputTokens. With a 60-token cap, gemini-3.8-flash spent 56
 *     tokens thinking and returned no text at all. Every call therefore asks for the answer
 *     budget PLUS thinking headroom, and an empty answer scores zero confidence.
 *   - gemini-3.8-flash rejects thinkingLevel "minimal" with a 400; "low" works. The level is
 *     configured per tier for that reason.
 *   - Thinking is billed as output, so it is metered as output.
 *
 * Refusals come as a blocked prompt or a safety/recitation finish. RECITATION means the model
 * would have reproduced source text verbatim, which this product must not do; it is treated
 * as a refusal, not as an answer.
 */
final class GeminiClient implements ModelClient, ReportsConfiguration
{
    private const REFUSALS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function __construct(
        private readonly GeminiTransport $transport,
        private readonly ConfidenceEstimator $confidence,
    ) {}

    /**
     * @param  list<RetrievedPassage>  $passages
     */
    public function complete(
        ResolvedPrompt $prompt,
        array $passages,
        string $question,
        string $tier = 'large',
    ): ModelResponse {
        $model = $this->modelFor($tier);

        $body = $this->generate($model, $tier, [
            'systemInstruction' => ['parts' => [['text' => $prompt->systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $this->userMessage($passages, $question)]]]],
        ], (int) config('ai.guardrails.max_answer_tokens', 1200));

        $refused = $this->refused($body);
        $text = $refused ? '' : $this->textFrom($body);

        return new ModelResponse(
            text: $text,
            model: $model,
            inputTokens: $this->inputTokens($body),
            outputTokens: $this->outputTokens($body),
            confidence: $refused ? 0.0 : $this->confidence->estimate($text, $passages, $this->truncated($body)),
            stopReason: $this->stopReason($body),
        );
    }

    /**
     * Structured extraction, constrained by the JSON Schema itself rather than by a request
     * in the prompt to "return only JSON".
     *
     * @param  array<string, mixed>  $schema
     */
    public function extract(string $instruction, string $content, array $schema, string $tier = 'large'): StructuredResponse
    {
        $model = $this->modelFor($tier);

        $body = $this->generate($model, $tier, [
            'systemInstruction' => ['parts' => [['text' => $instruction."\n\nUse null for anything not clearly stated. NEVER guess a date."]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $content]]]],
        ], 8192, [
            'responseMimeType' => 'application/json',
            'responseJsonSchema' => $schema === [] ? ['type' => 'object'] : $schema,
        ]);

        // A refusal carries no usable fields, even when some text came back with it.
        $text = $this->refused($body) ? '' : $this->textFrom($body);
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);
        $decoded = json_decode($text, true);

        return new StructuredResponse(
            data: is_array($decoded) ? $decoded : [],
            model: $model,
            inputTokens: $this->inputTokens($body),
            outputTokens: $this->outputTokens($body),
        );
    }

    /**
     * One step of an agent loop.
     *
     * The tool definitions arrive in the registry's provider-neutral shape (name, description,
     * input_schema) and are declared to Gemini as functions with the same JSON Schema. The
     * transcript is sent as text in a single user turn, so no provider-specific thought
     * signatures need to be carried between steps.
     *
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array<string, mixed>>  $transcript
     * @param  array<string, mixed>  $input
     */
    public function decide(
        string $system,
        array $tools,
        array $transcript,
        array $input,
        string $tier = 'large',
    ): AgentDecision {
        $model = $this->modelFor($tier);

        $parts = [['text' => json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}']];

        foreach ($transcript as $entry) {
            $parts[] = ['text' => ($entry['tool'] ?? $entry['role'] ?? 'observation').': '.($entry['content'] ?? '')];
        }

        $request = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => $parts]],
        ];

        if ($tools !== []) {
            $request['tools'] = [[
                'functionDeclarations' => array_map(static fn (array $tool): array => array_filter([
                    'name' => (string) $tool['name'],
                    'description' => $tool['description'] ?? null,
                    'parametersJsonSchema' => $tool['input_schema'] ?? null,
                ]), $tools),
            ]];
        }

        $body = $this->generate($model, $tier, $request, 8192);

        $inputTokens = $this->inputTokens($body);
        $outputTokens = $this->outputTokens($body);
        $cost = $this->costPaise($model, $inputTokens, $outputTokens);

        foreach ((array) data_get($body, 'candidates.0.content.parts', []) as $part) {
            if (is_array($part) && isset($part['functionCall']['name'])) {
                return AgentDecision::callTool(
                    toolKey: (string) $part['functionCall']['name'],
                    input: (array) ($part['functionCall']['args'] ?? []),
                    reasoning: $this->textFrom($body),
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    costPaise: $cost,
                );
            }
        }

        return AgentDecision::finish(
            output: ['text' => $this->refused($body) ? '' : $this->textFrom($body)],
            reasoning: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costPaise: $cost,
        );
    }

    public function isConfigured(): bool
    {
        return $this->transport->isConfigured();
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $generationConfig
     * @return array<string, mixed>
     */
    private function generate(string $model, string $tier, array $request, int $answerTokens, array $generationConfig = []): array
    {
        $request['generationConfig'] = [
            // The answer budget plus room to think. Without the headroom a cautious model
            // can spend the whole cap reasoning and return nothing.
            'maxOutputTokens' => $answerTokens + (int) config('ai.gemini.thinking_headroom', 4096),
            'thinkingConfig' => ['thinkingLevel' => (string) config("ai.gemini.thinking.{$tier}", 'low')],
            ...$generationConfig,
        ];

        return $this->transport->post("models/{$model}:generateContent", $request);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function textFrom(array $body): string
    {
        return collect((array) data_get($body, 'candidates.0.content.parts', []))
            ->filter(static fn (mixed $part): bool => is_array($part) && isset($part['text']) && ! ($part['thought'] ?? false))
            ->pluck('text')
            ->implode('');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function refused(array $body): bool
    {
        return filled(data_get($body, 'promptFeedback.blockReason'))
            || in_array((string) data_get($body, 'candidates.0.finishReason'), self::REFUSALS, true);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function truncated(array $body): bool
    {
        return data_get($body, 'candidates.0.finishReason') === 'MAX_TOKENS';
    }

    /**
     * The same vocabulary AnthropicClient reports, so nothing downstream has to know which
     * provider answered.
     *
     * @param  array<string, mixed>  $body
     */
    private function stopReason(array $body): ?string
    {
        if ($this->refused($body)) {
            return 'refusal';
        }

        $finish = data_get($body, 'candidates.0.finishReason');

        return match ($finish) {
            'STOP' => 'end_turn',
            'MAX_TOKENS' => 'max_tokens',
            null => null,
            default => strtolower((string) $finish),
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function inputTokens(array $body): int
    {
        return (int) data_get($body, 'usageMetadata.promptTokenCount', 0);
    }

    /**
     * Thinking is billed as output, so it is counted as output.
     *
     * @param  array<string, mixed>  $body
     */
    private function outputTokens(array $body): int
    {
        return (int) data_get($body, 'usageMetadata.candidatesTokenCount', 0)
            + (int) data_get($body, 'usageMetadata.thoughtsTokenCount', 0);
    }

    /**
     * The same passage layout AnthropicClient sends, so a prompt and its guard behave the
     * same whichever provider answers.
     *
     * @param  list<RetrievedPassage>  $passages
     */
    private function userMessage(array $passages, string $question): string
    {
        $rendered = collect($passages)
            ->map(fn (RetrievedPassage $p, int $i): string => '['.($i + 1)."] {$p->content}")
            ->implode("\n\n");

        return "Passages:\n{$rendered}\n\nQuestion: {$question}";
    }

    private function modelFor(string $tier): string
    {
        return (string) config("ai.gemini.models.{$tier}", config('ai.gemini.models.large'));
    }

    private function costPaise(string $model, int $input, int $output): int
    {
        // Not config("ai.pricing.{$model}"): the dots in "gemini-3.8-flash" are read as nesting.
        return AiPricing::costPaise($model, $input, $output);
    }
}
