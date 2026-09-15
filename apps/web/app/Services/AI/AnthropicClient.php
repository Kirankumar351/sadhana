<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\Agents\AgentDecision;
use App\Services\AI\Contracts\ModelClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The only class in this application that talks to a model provider.
 *
 * An architecture test fails the build if anything outside App\Services\AI references the
 * provider, because the moment a feature can call the API itself, every cost cap, refusal
 * log and output filter in AiGateway becomes optional — and the one that gets skipped will
 * be the one that mattered.
 */
final class AnthropicClient implements ModelClient
{
    private const API_VERSION = '2023-06-01';

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

        $body = $this->post($this->withoutThinking($model, [
            'model' => $model,
            'max_tokens' => (int) config('ai.guardrails.max_answer_tokens', 1200),
            'system' => $prompt->systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => $this->userMessage($passages, $question),
            ]],
        ]));

        // A declined request is not an answer, whatever partial text came with it. Zero
        // confidence hands the student to the community instead of showing a fragment.
        $refused = data_get($body, 'stop_reason') === 'refusal';

        return new ModelResponse(
            text: $refused ? '' : $this->textFrom($body),
            model: $model,
            inputTokens: (int) data_get($body, 'usage.input_tokens', 0),
            outputTokens: (int) data_get($body, 'usage.output_tokens', 0),
            confidence: $refused ? 0.0 : $this->confidenceFrom($body, $passages),
            stopReason: data_get($body, 'stop_reason'),
        );
    }

    /**
     * Structured extraction, used by the notification extractor and the news pipeline.
     *
     * "Never guess a date" is load-bearing in the instruction, and everything returned here
     * still goes to human review regardless — a hallucinated deadline is the worst failure
     * this product can produce.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function extract(string $instruction, string $content, array $schema, string $tier = 'large'): StructuredResponse
    {
        $model = $this->modelFor($tier);

        $body = $this->post($this->withoutThinking($model, [
            'model' => $model,
            // Room to finish. JSON cut off at the cap does not decode, and an object that
            // fails to decode reads downstream as "nothing was extracted".
            'max_tokens' => 16000,
            'system' => $instruction."\n\nReturn ONLY valid JSON matching the given schema. "
                .'Use null for anything not clearly stated. NEVER guess a date.',
            'messages' => [[
                'role' => 'user',
                'content' => "JSON schema:\n".json_encode($schema, JSON_PRETTY_PRINT)."\n\nContent:\n".$content,
            ]],
        ]));

        // A refusal carries no usable fields, even when some text came back with it.
        $text = data_get($body, 'stop_reason') === 'refusal' ? '' : $this->textFrom($body);

        // Models wrap JSON in a fenced block more often than not, whatever the prompt says.
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);

        $decoded = json_decode($text, true);

        return new StructuredResponse(
            data: is_array($decoded) ? $decoded : [],
            model: $model,
            inputTokens: (int) data_get($body, 'usage.input_tokens', 0),
            outputTokens: (int) data_get($body, 'usage.output_tokens', 0),
        );
    }

    /**
     * Short, bounded calls run without extended thinking.
     *
     * Sonnet 5 and Opus 5 think by default when `thinking` is omitted, and thinking tokens
     * count against `max_tokens`. Under a 1,200-token answer cap the model could spend the
     * whole budget reasoning and return no text — which the caller would read as an empty
     * answer rather than an error. Grounded answers and JSON extraction do not need it.
     *
     * Haiku 4.5 does not think unless asked, and the Fable and Mythos models reject a
     * disabled setting outright, so those are sent unchanged.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutThinking(string $model, array $payload): array
    {
        foreach (['claude-haiku', 'claude-fable', 'claude-mythos'] as $family) {
            if (str_starts_with($model, $family)) {
                return $payload;
            }
        }

        return $payload + ['thinking' => ['type' => 'disabled']];
    }

    /**
     * One step of an agent loop.
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

        $messages = [[
            'role' => 'user',
            'content' => json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}',
        ]];

        foreach ($transcript as $entry) {
            $messages[] = [
                'role' => 'user',
                'content' => ($entry['tool'] ?? $entry['role'] ?? 'observation').': '.($entry['content'] ?? ''),
            ];
        }

        $body = $this->post(array_filter([
            'model' => $model,
            // Agent steps keep the model's default thinking — choosing the next tool is the
            // reasoning — so the cap leaves room for thinking and the tool call together.
            'max_tokens' => 16000,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools ?: null,
        ]));

        $inputTokens = (int) data_get($body, 'usage.input_tokens', 0);
        $outputTokens = (int) data_get($body, 'usage.output_tokens', 0);
        $cost = $this->costPaise($model, $inputTokens, $outputTokens);

        // A tool_use block means another step; anything else ends the run.
        foreach (data_get($body, 'content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                return AgentDecision::callTool(
                    toolKey: (string) $block['name'],
                    input: (array) ($block['input'] ?? []),
                    reasoning: $this->textFrom($body),
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    costPaise: $cost,
                );
            }
        }

        return AgentDecision::finish(
            output: ['text' => $this->textFrom($body)],
            reasoning: null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costPaise: $cost,
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        $key = config('ai.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])
            ->timeout(60)
            // Retry on transient failures only. A 4xx is our bug and retrying it just
            // burns budget; a 429 or 5xx is worth a second attempt.
            ->retry(2, 1000, fn ($e, $request) => $e instanceof ConnectionException
                || ($e->response?->status() >= 500)
                || ($e->response?->status() === 429))
            ->post(rtrim((string) config('ai.anthropic.base_url'), '/').'/v1/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Model provider error: '.$response->status().' '.$response->body());
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function textFrom(array $body): string
    {
        return collect(data_get($body, 'content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    /**
     * @param  list<RetrievedPassage>  $passages
     */
    private function userMessage(array $passages, string $question): string
    {
        $rendered = collect($passages)
            ->map(fn (RetrievedPassage $p, int $i): string => '['.($i + 1)."] {$p->content}")
            ->implode("\n\n");

        return "Passages:\n{$rendered}\n\nQuestion: {$question}";
    }

    /**
     * A confidence estimate, since the provider does not return one.
     *
     * Deliberately crude and deliberately pessimistic. It combines retrieval strength with
     * whether the model hedged, and it only ever REDUCES confidence — an answer that says
     * "I could not find" must fall below the floor and hand over to the community, however
     * fluent it reads.
     *
     * @param  array<string, mixed>  $body
     * @param  list<RetrievedPassage>  $passages
     */
    private function confidenceFrom(array $body, array $passages): float
    {
        $scores = array_map(static fn (RetrievedPassage $p): float => $p->score, $passages);
        $retrieval = $scores === [] ? 0.0 : max($scores);

        $text = mb_strtolower($this->textFrom($body));

        $hedges = [
            'i could not find', 'i do not have', 'not in the passages', 'unclear',
            'నా దగ్గర లేదు', 'కనుగొనలేకపోయాను',
        ];

        foreach ($hedges as $hedge) {
            if (str_contains($text, $hedge)) {
                return 0.2;
            }
        }

        // A truncated answer is an incomplete answer, whatever its content.
        if (data_get($body, 'stop_reason') === 'max_tokens') {
            return min($retrieval, 0.5);
        }

        return round(min($retrieval, 1.0), 3);
    }

    private function modelFor(string $tier): string
    {
        return (string) config("ai.models.{$tier}", config('ai.models.large'));
    }

    private function costPaise(string $model, int $input, int $output): int
    {
        /** @var array{input: float, output: float}|null $rate */
        $rate = config("ai.pricing.{$model}");

        if ($rate === null) {
            return 0;
        }

        return (int) ceil($input / 1_000_000 * $rate['input'] + $output / 1_000_000 * $rate['output']);
    }
}
