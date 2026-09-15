<?php

declare(strict_types=1);

use App\Models\AiChunk;
use App\Models\ExamNotification;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ReportsConfiguration;
use App\Services\AI\Contracts\VectorStore;
use App\Services\AI\QdrantStore;
use Illuminate\Support\Facades\Http;

/**
 * Reindexing when the vector store is down.
 *
 * Found by the test suite itself: with a real embedding key in .env and no Qdrant running,
 * every save of a notification embedded its text — spending real money — and then crashed
 * trying to store vectors that had nowhere to go. The job is on a queue in production, but
 * the money was spent either way, and the failure was reported three times per save.
 */
it('spends nothing on embeddings and leaves chunks stale when the vector store is unreachable', function (): void {
    Http::fake(['127.0.0.1:6333/*' => Http::failedConnection()]);
    config(['ai.vector.host' => 'http://127.0.0.1:6333']);

    $embedder = new class implements EmbeddingClient, ReportsConfiguration
    {
        public int $calls = 0;

        public function embed(string $text): array
        {
            $this->calls++;

            return [0.1];
        }

        public function embedBatch(array $texts): array
        {
            $this->calls++;

            return array_map(fn (): array => [0.1], $texts);
        }

        public function model(): string
        {
            return 'spy-embedder';
        }

        public function dimensions(): int
        {
            return 1;
        }

        public function isConfigured(): bool
        {
            return true;
        }
    };

    app()->instance(EmbeddingClient::class, $embedder);
    app()->instance(VectorStore::class, new QdrantStore);

    // Saving a published notification reindexes it through the observer, synchronously here.
    $notification = ExamNotification::create([
        'slug' => 'appsc-group-1-2026',
        'title' => ['en' => 'APPSC Group-I Services 2026'],
        'description' => ['en' => 'Applications from 06/10/2026 to 27/10/2026.'],
        'organisation' => 'Andhra Pradesh Public Service Commission',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect($embedder->calls)->toBe(0)
        ->and(AiChunk::query()->where('source_id', $notification->id)->where('is_stale', false)->count())->toBe(0);
});
