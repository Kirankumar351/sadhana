<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Embeddings for the retrieval corpus.
 *
 * `model()` is exposed and recorded on every `ai_chunks` row for a reason that is easy to
 * miss: vectors produced by two different models are not comparable, so changing the
 * embedding model silently invalidates the entire corpus. Retrieval quality would degrade
 * for months with nothing ever failing. Recording the model is what lets `corpus:reindex`
 * find and re-embed everything that predates a change.
 */
final class VoyageEmbedder implements EmbeddingClient
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $key = config('ai.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('No embedding API key configured.');
        }

        $out = [];

        // Batched because the corpus backfill embeds tens of thousands of chunks, and one
        // request per chunk would be both slow and needlessly expensive. Batches of 128
        // rather than 1000: a failed batch of 128 is cheap to retry.
        foreach (array_chunk($texts, 128) as $batch) {
            $response = Http::withToken((string) $key)
                ->timeout(60)
                ->retry(3, 2000)
                ->post('https://api.voyageai.com/v1/embeddings', [
                    'model' => $this->model(),
                    'input' => $batch,
                    // Documents, not queries. Voyage scores asymmetrically, and the wrong
                    // type here quietly costs retrieval quality with nothing failing.
                    'input_type' => 'document',
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Embedding provider error: '.$response->status());
            }

            foreach ($response->json('data', []) as $item) {
                $out[] = $item['embedding'];
            }
        }

        return $out;
    }

    public function model(): string
    {
        return (string) config('ai.models.embedding', 'voyage-3');
    }

    public function dimensions(): int
    {
        return (int) config('ai.models.embedding_dimensions', 1024);
    }
}
