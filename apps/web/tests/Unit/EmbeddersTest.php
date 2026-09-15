<?php

declare(strict_types=1);

use App\Services\AI\GeminiEmbedder;
use App\Services\AI\VoyageEmbedder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Both embedding providers.
 *
 * The two silent failures here cost retrieval quality without anything erroring: a question
 * embedded as a document, and vectors of the wrong size or count attached to the wrong
 * chunks. The third was louder but no less real — the Voyage client authenticated with the
 * Anthropic key, so it could never have worked at all.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();

    config([
        'ai.gemini.key' => 'test-gemini-key',
        'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com',
        'ai.gemini.models.embedding' => 'gemini-embedding-2',
        'ai.models.embedding' => 'voyage-3',
        'ai.models.embedding_dimensions' => 1024,
    ]);
});

it('embeds corpus chunks with Gemini as documents, in batches of 100, at the collection size', function (): void {
    Http::fake(fn (Request $request) => Http::response([
        'embeddings' => array_fill(0, count($request['requests']), ['values' => [0.1, 0.2, 0.3]]),
    ]));

    $vectors = app(GeminiEmbedder::class)->embedBatch(array_map(fn (int $i): string => "chunk {$i}", range(1, 150)));

    expect($vectors)->toHaveCount(150);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1beta/models/gemini-embedding-2:batchEmbedContents')
        && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
        && count($request['requests']) === 100
        && $request['requests'][0]['taskType'] === 'RETRIEVAL_DOCUMENT'
        && $request['requests'][0]['outputDimensionality'] === 1024);
});

it('embeds a student question with Gemini as a query', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embeddings' => [['values' => [0.5, 0.5]]]])]);

    expect(app(GeminiEmbedder::class)->embed('గ్రూప్ 2 సిలబస్ ఏమిటి?'))->toBe([0.5, 0.5]);

    Http::assertSent(fn (Request $request): bool => $request['requests'][0]['taskType'] === 'RETRIEVAL_QUERY');
});

it('refuses a Gemini response with the wrong number of vectors', function (): void {
    // One vector short would shift every vector onto the wrong chunk.
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['embeddings' => [['values' => [0.1]]]])]);

    expect(fn () => app(GeminiEmbedder::class)->embedBatch(['first', 'second']))
        ->toThrow(RuntimeException::class, 'returned 1 embeddings for 2 inputs');
});

it('authenticates Voyage with the Voyage key, never the Anthropic one', function (): void {
    config(['ai.voyage.key' => 'voyage-key', 'ai.anthropic.key' => 'anthropic-key']);

    Http::fake(['api.voyageai.com/*' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]])]);

    app(VoyageEmbedder::class)->embed('What is Group 2?');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer voyage-key')
        && $request['input_type'] === 'query');
});

it('embeds corpus chunks with Voyage as documents', function (): void {
    config(['ai.voyage.key' => 'voyage-key']);

    Http::fake(['api.voyageai.com/*' => Http::response(['data' => [['embedding' => [0.1]], ['embedding' => [0.2]]]])]);

    expect(app(VoyageEmbedder::class)->embedBatch(['a', 'b']))->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => $request['input_type'] === 'document');
});

it('treats Voyage as unconfigured without a Voyage key, whatever else is set', function (): void {
    config(['ai.voyage.key' => null, 'ai.anthropic.key' => 'anthropic-key']);

    expect(app(VoyageEmbedder::class)->isConfigured())->toBeFalse();
});
