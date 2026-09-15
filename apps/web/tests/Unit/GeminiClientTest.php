<?php

declare(strict_types=1);

use App\Services\AI\AiPricing;
use App\Services\AI\GeminiClient;
use App\Services\AI\ResolvedPrompt;
use App\Services\AI\RetrievedPassage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * The Gemini client's request shape and its handling of what Gemini actually returns.
 *
 * Every case here was observed against the live API before it was written down: thinking
 * that consumes the whole output cap and returns no text, "503 high demand" on a first
 * attempt, and "429 quota exceeded" on a free-tier key. No request here reaches Google.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();

    config([
        'ai.gemini.key' => 'test-gemini-key',
        'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com',
        'ai.gemini.models.large' => 'gemini-3.8-flash',
        'ai.gemini.models.small' => 'gemini-3.5-flash-lite',
        'ai.gemini.thinking.large' => 'low',
        'ai.gemini.thinking.small' => 'minimal',
        'ai.gemini.thinking_headroom' => 4096,
        'ai.gemini.retries' => 2,
        'ai.gemini.max_retry_wait' => 10,
        'ai.guardrails.max_answer_tokens' => 1200,
    ]);
});

/**
 * @param  list<array<string, mixed>>  $parts
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function geminiReply(array $parts, string $finish = 'STOP', array $extra = []): array
{
    return $extra + [
        'candidates' => [['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => $finish]],
        'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 80, 'thoughtsTokenCount' => 300],
        'modelVersion' => 'gemini-3.8-flash',
    ];
}

/**
 * @return list<RetrievedPassage>
 */
function geminiPassages(): array
{
    return [new RetrievedPassage(
        chunkId: 1,
        sourceType: 'notification',
        sourceId: 1,
        content: 'APPSC Group-I applications run from 06/10/2026 to 27/10/2026.',
        score: 0.91,
        locale: 'en',
        title: 'APPSC Notification 07/2026',
    )];
}

function geminiPrompt(): ResolvedPrompt
{
    return new ResolvedPrompt(key: 'grounded_answer', version: 'v1', systemPrompt: 'Answer only from the passages.');
}

it('extracts JSON constrained by the schema, with room to think and the key in a header', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply([['text' => '{"title":"APPSC Group-I"}']]))]);

    $result = app(GeminiClient::class)->extract('Extract the notification.', 'text', ['type' => 'object']);

    expect($result->data)->toBe(['title' => 'APPSC Group-I'])
        ->and($result->model)->toBe('gemini-3.8-flash')
        ->and($result->inputTokens)->toBe(1200)
        // Thinking is billed as output, so it is metered as output.
        ->and($result->outputTokens)->toBe(380);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1beta/models/gemini-3.8-flash:generateContent')
        && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
        && $request['generationConfig']['responseMimeType'] === 'application/json'
        && $request['generationConfig']['responseJsonSchema'] === ['type' => 'object']
        && $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'low']
        && $request['generationConfig']['maxOutputTokens'] === 8192 + 4096);
});

it('uses the small model with minimal thinking for small-tier work', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply([['text' => '{"relevant":true}']]))]);

    app(GeminiClient::class)->extract('Classify.', 'RBI cuts repo rate', ['type' => 'object'], tier: 'small');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent')
        && $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'minimal']);
});

it('answers from passages with the answer budget plus thinking headroom', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply([
        ['text' => 'thinking about dates', 'thought' => true],
        ['text' => 'Applications run from 6 to 27 October 2026.'],
    ]))]);

    $response = app(GeminiClient::class)->complete(geminiPrompt(), geminiPassages(), 'When does APPSC Group-I close?');

    expect($response->text)->toBe('Applications run from 6 to 27 October 2026.')
        ->and($response->confidence)->toBe(0.91)
        ->and($response->stopReason)->toBe('end_turn');

    Http::assertSent(fn (Request $request): bool => $request['generationConfig']['maxOutputTokens'] === 1200 + 4096
        && $request['systemInstruction']['parts'][0]['text'] === 'Answer only from the passages.'
        && str_contains($request['contents'][0]['parts'][0]['text'], '[1] APPSC Group-I applications'));
});

it('scores an answer that thinking crowded out as zero confidence, never as an empty reply', function (): void {
    // Observed live: a 60-token cap, 56 tokens of thinking, MAX_TOKENS, and no text at all.
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply(
        [['text' => 'still thinking', 'thought' => true]],
        'MAX_TOKENS',
    ))]);

    $response = app(GeminiClient::class)->complete(geminiPrompt(), geminiPassages(), 'When does it close?');

    expect($response->text)->toBe('')
        ->and($response->confidence)->toBe(0.0)
        ->and($response->stopReason)->toBe('max_tokens');
});

it('treats a blocked prompt or a refusal finish as no answer', function (array $reply): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response($reply)]);

    $response = app(GeminiClient::class)->complete(geminiPrompt(), geminiPassages(), 'Question');

    expect($response->text)->toBe('')
        ->and($response->confidence)->toBe(0.0)
        ->and($response->stopReason)->toBe('refusal');
})->with([
    'blocked prompt' => [['promptFeedback' => ['blockReason' => 'SAFETY'], 'usageMetadata' => ['promptTokenCount' => 10]]],
    // Recitation means verbatim reproduction of source text, which this product must not do.
    'recitation' => [geminiReply([['text' => 'A verbatim passage from a textbook']], 'RECITATION')],
]);

it('calls a tool when Gemini returns a function call, declaring tools with their JSON Schema', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply([
        ['functionCall' => ['name' => 'raise_alert', 'args' => ['message' => 'AI spend is 41% of revenue'], 'id' => 'call_1']],
    ]))]);

    $schema = ['type' => 'object', 'properties' => ['message' => ['type' => 'string']], 'required' => ['message']];

    $decision = app(GeminiClient::class)->decide(
        system: 'You are the cost-watch agent.',
        tools: [['name' => 'raise_alert', 'description' => 'Raise an ops alert.', 'input_schema' => $schema]],
        transcript: [['tool' => 'read_costs', 'content' => '41%']],
        input: ['ai_spend_percent_of_revenue' => 41],
    );

    expect($decision->toolKey)->toBe('raise_alert')
        ->and($decision->toolInput)->toBe(['message' => 'AI spend is 41% of revenue']);

    Http::assertSent(fn (Request $request): bool => $request['tools'][0]['functionDeclarations'][0]['name'] === 'raise_alert'
        && $request['tools'][0]['functionDeclarations'][0]['parametersJsonSchema'] === $schema
        && count($request['contents']) === 1
        && $request['contents'][0]['parts'][1]['text'] === 'read_costs: 41%');
});

it('finishes the agent step when no tool is called', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply([['text' => 'DONE']]))]);

    $decision = app(GeminiClient::class)->decide('Agent.', [], [], ['ok' => true]);

    expect($decision->toolKey)->toBeNull()
        ->and($decision->output)->toBe(['text' => 'DONE']);
});

it('retries a 503 and succeeds', function (): void {
    // Observed live on the first attempt of almost every call.
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'high demand']], 503)
        ->push(geminiReply([['text' => '{"ok":true}']]))]);

    $result = app(GeminiClient::class)->extract('Extract.', 'text', ['type' => 'object']);

    expect($result->data)->toBe(['ok' => true]);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

it('does not make a student wait out a quota that resets minutes away', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => [
        'code' => 429,
        'status' => 'RESOURCE_EXHAUSTED',
        'message' => 'You exceeded your current quota',
        'details' => [['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '120s']],
    ]], 429)]);

    expect(fn () => app(GeminiClient::class)->extract('Extract.', 'text', ['type' => 'object']))
        ->toThrow(RuntimeException::class, 'Model provider error: 429');

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('refuses to call without a key and says which one is missing', function (): void {
    config(['ai.gemini.key' => null]);

    expect(app(GeminiClient::class)->isConfigured())->toBeFalse()
        ->and(fn () => app(GeminiClient::class)->extract('Extract.', 'text', []))
        ->toThrow(RuntimeException::class, 'GEMINI_API_KEY');

    Http::assertNothingSent();
});

it('has a price for every Gemini model the application is configured to call', function (): void {
    // A model missing from the price table records zero cost, and a feature that looks free
    // is how an AI bill goes unnoticed for a month.
    foreach (['small', 'large', 'embedding'] as $model) {
        expect(AiPricing::rateFor((string) config("ai.gemini.models.{$model}")))->not->toBeNull();
    }
});

it('prices a model id that contains dots', function (): void {
    // config("ai.pricing.gemini-3.8-flash") reads the dots as nesting and finds nothing, which
    // metered every Gemini call at zero and left its cost uncapped.
    expect(AiPricing::rateFor('gemini-3.8-flash'))->toBe(['input' => 6_375.0, 'output' => 31_875.0])
        ->and(AiPricing::costPaise('gemini-3.8-flash', 1_000_000, 1_000_000))->toBe(38_250);
});
