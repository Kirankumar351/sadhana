<?php

declare(strict_types=1);

use App\Jobs\ReindexChunks;
use App\Models\AiChunk;
use App\Models\ExamNotification;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\QdrantStore;
use App\Services\AI\Retriever;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeAi;

/**
 * Three things that kept Ask, Explain and the doubt solver from ever answering.
 *
 * All three were found by running the pipeline for real, not by reading it.
 */
it('embeds chunks that were built before any embedding key existed', function (): void {
    // No provider key in tests, so saving builds the chunks and leaves them stale.
    $notification = ExamNotification::create([
        'slug' => 'ssc-cgl-2026',
        'title' => ['en' => 'SSC Combined Graduate Level 2026'],
        'description' => ['en' => 'Applications are invited for 17,727 posts.'],
        'organisation' => 'Staff Selection Commission',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect(AiChunk::query()->where('source_id', $notification->id)->where('is_stale', true)->count())->toBeGreaterThan(0);

    // A key arrives. The chunk text has not changed, which is exactly the case the job
    // used to skip: it only embedded chunks whose text changed in that same run.
    FakeAi::install();
    ReindexChunks::dispatchSync(ExamNotification::class, $notification->id);

    expect(AiChunk::query()->where('source_id', $notification->id)->where('is_stale', true)->count())->toBe(0)
        ->and(AiChunk::query()->where('source_id', $notification->id)->value('embedding_model'))->toBe('fake-embedder');
});

it('lets Explain answer from the single passage it was given', function (): void {
    FakeAi::install('Plain language version.', [
        ['content' => 'Article 243 defines the Panchayat as an institution of self-government.', 'locale' => 'te'],
    ]);

    $result = app(AiGateway::class)->ask('Explain this passage in plain language.', User::factory()->create(), 'explain');

    expect($result->status)->toBe('answered');
});

it('still refuses an ordinary question grounded in a single passage', function (): void {
    FakeAi::install('An answer.', [
        ['content' => 'SSC CGL has four tiers.', 'locale' => 'te'],
    ]);

    $result = app(AiGateway::class)->ask('What is the SSC CGL pattern?', User::factory()->create(), 'ask');

    expect($result->status)->toBe('no_sources');
});

it('scopes Explain to the material being read', function (): void {
    Http::fake(['127.0.0.1:6333/*' => Http::response(['result' => ['points' => []]])]);
    config(['ai.vector.host' => 'http://127.0.0.1:6333']);

    $embedder = Mockery::mock(EmbeddingClient::class);
    $embedder->shouldReceive('embed')->andReturn([0.1, 0.2]);

    (new Retriever($embedder, new QdrantStore))->retrieve('Explain this', 'te', ['material_id' => 42]);

    Http::assertSent(function (Request $request): bool {
        $must = collect($request['filter']['must'] ?? []);

        return $must->contains(fn (array $c): bool => $c['key'] === 'source_type' && ($c['match']['value'] ?? null) === 'material')
            && $must->contains(fn (array $c): bool => $c['key'] === 'source_id' && ($c['match']['value'] ?? null) === 42);
    });
});
