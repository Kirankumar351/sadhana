<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

/**
 * Vector search boundary.
 *
 * Implemented by Qdrant. The interface exists because the source documents assumed a
 * MySQL VECTOR column, which MySQL 8 does not have — so the store is genuinely a swappable
 * decision, and moving to pgvector or MySQL 9 later should be one class, not a migration
 * of every AI feature.
 *
 * MySQL remains the owner of record: `ai_chunks.id` is the point id here. Deleting a chunk
 * must delete its point, or the assistant keeps quoting material that was taken down.
 */
interface VectorStore
{
    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, score: float}>
     */
    public function search(array $vector, int $limit, array $filters = []): array;

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $payload
     */
    public function upsert(int $chunkId, array $vector, array $payload): void;

    /**
     * @param  list<int>  $chunkIds
     */
    public function delete(array $chunkIds): void;

    /**
     * Remove every point belonging to one owner row — the deletion path that a copyright
     * takedown depends on.
     */
    public function deleteBySource(string $sourceType, int $sourceId): void;
}
