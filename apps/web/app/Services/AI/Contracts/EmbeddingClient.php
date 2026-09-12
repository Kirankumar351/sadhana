<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

/**
 * Embedding boundary.
 *
 * `model()` is exposed because `ai_chunks.embedding_model` records which model produced
 * each vector. Changing the embedding model invalidates the whole corpus — vectors from
 * two different models are not comparable — so the recorded value is what lets
 * `corpus:reindex` find and re-embed everything that predates the change, rather than
 * silently degrading retrieval for months.
 */
interface EmbeddingClient
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * Batched, because the corpus backfill embeds tens of thousands of chunks and one
     * request per chunk would be both slow and needlessly expensive.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;

    public function model(): string;

    public function dimensions(): int;
}
