<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ReportsConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Embeddings for the retrieval corpus, from Voyage.
 *
 * `model()` is exposed and recorded on every `ai_chunks` row for a reason that is easy to
 * miss: vectors produced by two different models are not comparable, so changing the
 * embedding model silently invalidates the entire corpus. Retrieval quality would degrade
 * for months with nothing ever failing. Recording the model is what lets `corpus:reindex`
 * find and re-embed everything that predates a change.
 *
 * Two bugs lived here. It authenticated with the ANTHROPIC key — Voyage is a separate company
 * that issues its own keys, so no embedding request could ever have succeeded. And it embedded
 * a student's question as a "document", when Voyage scores queries and documents
 * asymmetrically and the wrong type quietly costs retrieval quality with nothing failing.
 */
final class VoyageEmbedder implements EmbeddingClient, ReportsConfiguration
{
    private const BATCH = 128;

    /**
     * Called by Retriever with a student's question.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->request([$text], 'query')[0] ?? [];
    }

    /**
     * Called by ReindexChunks with corpus chunks. Batches of 128 rather than 1000: a failed
     * batch of 128 is cheap to retry.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        $out = [];

        foreach (array_chunk($texts, self::BATCH) as $batch) {
            array_push($out, ...$this->request($batch, 'document'));
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

    public function isConfigured(): bool
    {
        return filled(config('ai.voyage.key'));
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function request(array $texts, string $inputType): array
    {
        if ($texts === []) {
            return [];
        }

        $key = (string) config('ai.voyage.key');

        if ($key === '') {
            throw new RuntimeException('VOYAGE_API_KEY is not set.');
        }

        $response = Http::withToken($key)
            ->timeout(60)
            ->retry(3, 2000, throw: false)
            ->post('https://api.voyageai.com/v1/embeddings', [
                'model' => $this->model(),
                'input' => $texts,
                'input_type' => $inputType,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding provider error: '.$response->status());
        }

        return array_map(
            static fn (array $item): array => array_map('floatval', (array) ($item['embedding'] ?? [])),
            (array) $response->json('data', []),
        );
    }
}
