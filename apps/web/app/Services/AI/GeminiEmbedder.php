<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ReportsConfiguration;
use RuntimeException;

/**
 * Embeddings from Gemini, at the dimension the vector collection was built for.
 *
 * `outputDimensionality` is set explicitly to the collection's size (1024), verified against
 * the live API for both gemini-embedding-001 and gemini-embedding-2. A provider's default
 * size that differs from the collection would fail at upsert, or worse, at query time.
 * Truncated Gemini vectors are not unit length, which does not matter here: the collection
 * uses cosine distance, which ignores magnitude.
 *
 * QUERIES AND DOCUMENTS ARE EMBEDDED DIFFERENTLY. Gemini scores asymmetrically, so the
 * corpus is embedded as RETRIEVAL_DOCUMENT and a student's question as RETRIEVAL_QUERY.
 * `embed()` is only ever called with a query (Retriever) and `embedBatch()` only with
 * corpus chunks (ReindexChunks), which is what makes that split safe.
 *
 * Vectors from different embedding models are not comparable. Every ai_chunks row records
 * the model that produced it, so switching provider means `corpus:reindex` — not a silent
 * mix of incomparable vectors.
 */
final class GeminiEmbedder implements EmbeddingClient, ReportsConfiguration
{
    /** The batch endpoint's per-request limit. */
    private const BATCH = 100;

    public function __construct(private readonly GeminiTransport $transport) {}

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->request([$text], 'RETRIEVAL_QUERY')[0] ?? [];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array
    {
        $out = [];

        foreach (array_chunk($texts, self::BATCH) as $batch) {
            array_push($out, ...$this->request($batch, 'RETRIEVAL_DOCUMENT'));
        }

        return $out;
    }

    public function model(): string
    {
        return (string) config('ai.gemini.models.embedding', 'gemini-embedding-2');
    }

    public function dimensions(): int
    {
        return (int) config('ai.models.embedding_dimensions', 1024);
    }

    public function isConfigured(): bool
    {
        return $this->transport->isConfigured();
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function request(array $texts, string $task): array
    {
        if ($texts === []) {
            return [];
        }

        $model = $this->model();

        $body = $this->transport->post("models/{$model}:batchEmbedContents", [
            'requests' => array_map(fn (string $text): array => [
                'model' => "models/{$model}",
                'content' => ['parts' => [['text' => $text]]],
                'taskType' => $task,
                'outputDimensionality' => $this->dimensions(),
            ], $texts),
        ]);

        $vectors = array_map(
            static fn (array $embedding): array => array_map('floatval', (array) ($embedding['values'] ?? [])),
            (array) ($body['embeddings'] ?? []),
        );

        // One vector per input, in order, or none at all. A short response silently shifted
        // by one would attach every vector to the wrong chunk.
        if (count($vectors) !== count($texts)) {
            throw new RuntimeException('Gemini returned '.count($vectors).' embeddings for '.count($texts).' inputs.');
        }

        return $vectors;
    }
}
