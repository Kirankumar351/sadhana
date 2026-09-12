<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\VectorStore;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vector storage.
 *
 * MySQL 8 has no VECTOR type, so `ai_chunks` stays the MySQL owner of record for chunk text
 * and provenance while the vectors live here, keyed by `ai_chunks.id` as the point id. One
 * number, two stores, and no mapping table to fall out of sync.
 *
 * `deleteBySource()` is the method that makes a copyright takedown complete. If material is
 * removed but its vectors stay, the assistant keeps quoting it — the failure mode with
 * legal consequences rather than merely embarrassing ones.
 */
final class QdrantStore implements VectorStore
{
    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $filters
     * @return list<array{id: int, score: float}>
     */
    public function search(array $vector, int $limit, array $filters = []): array
    {
        $response = $this->client()->post("/collections/{$this->collection()}/points/query", array_filter([
            'query' => $vector,
            'limit' => $limit,
            'with_payload' => false,
            'filter' => $this->buildFilter($filters),
        ]));

        if ($response->failed()) {
            // Retrieval being down must degrade to "ask the community", never to a 500 on
            // whatever page the assistant happens to sit on.
            report(new RuntimeException('Qdrant search failed: '.$response->status()));

            return [];
        }

        return array_map(
            static fn (array $point): array => ['id' => (int) $point['id'], 'score' => (float) $point['score']],
            $response->json('result.points', []),
        );
    }

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $payload
     */
    public function upsert(int $chunkId, array $vector, array $payload): void
    {
        $this->upsertMany([[$chunkId, $vector, $payload]]);
    }

    /**
     * @param  list<array{0: int, 1: list<float>, 2: array<string, mixed>}>  $items
     */
    public function upsertMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->ensureCollection();

        $this->client()->put("/collections/{$this->collection()}/points", [
            'points' => array_map(static fn (array $item): array => [
                'id' => $item[0],
                'vector' => $item[1],
                'payload' => $item[2],
            ], $items),
        ])->throw();
    }

    /**
     * @param  list<int>  $chunkIds
     */
    public function delete(array $chunkIds): void
    {
        if ($chunkIds === []) {
            return;
        }

        $this->tryDelete(['points' => array_values($chunkIds)]);
    }

    public function deleteBySource(string $sourceType, int $sourceId): void
    {
        $this->tryDelete(['filter' => ['must' => [
            ['key' => 'source_type', 'match' => ['value' => $sourceType]],
            ['key' => 'source_id', 'match' => ['value' => $sourceId]],
        ]]]);
    }

    /**
     * Deletion is best-effort against the vector store, and that is deliberate.
     *
     * `ai_chunks` in MySQL is the owner of record, and Retriever joins every hit back to it
     * before returning a passage. So a chunk deleted in MySQL can never be served even if
     * its Qdrant point outlives it by a few minutes.
     *
     * That ordering is what makes a copyright takedown safe when the vector store happens
     * to be unreachable: the removal still completes where it counts, and the orphaned
     * point is swept by the next `corpus:reindex`. Throwing here would instead abort the
     * takedown entirely, which is the far worse outcome.
     *
     * @param  array<string, mixed>  $payload
     */
    private function tryDelete(array $payload): void
    {
        try {
            $this->client()->post("/collections/{$this->collection()}/points/delete", $payload);
        } catch (\Throwable $e) {
            report(new RuntimeException(
                'Qdrant delete failed; MySQL chunks were still removed so nothing can be '
                .'served. Orphaned points will be swept by the next corpus:reindex. '
                .$e->getMessage()
            ));
        }
    }

    public function ensureCollection(): void
    {
        $name = $this->collection();

        if ($this->client()->get("/collections/{$name}")->successful()) {
            return;
        }

        $this->client()->put("/collections/{$name}", [
            'vectors' => [
                'size' => (int) config('ai.models.embedding_dimensions', 1024),
                'distance' => (string) config('ai.vector.distance', 'Cosine'),
            ],
        ])->throw();

        // Without payload indexes a scoped search ("ask about this job") degrades to a full
        // scan — and that is exactly the query shape used on the busiest pages.
        foreach (['source_type', 'source_id', 'exam_id', 'locale'] as $field) {
            $this->client()->put("/collections/{$name}/index", [
                'field_name' => $field,
                'field_schema' => 'keyword',
            ]);
        }
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('ai.vector.host'), '/'))->timeout(20);

        if ($key = config('ai.vector.api_key')) {
            $request = $request->withHeaders(['api-key' => (string) $key]);
        }

        return $request;
    }

    private function collection(): string
    {
        return (string) config('ai.vector.collection', 'sadhana_chunks');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function buildFilter(array $filters): ?array
    {
        $must = [];

        foreach (['source_type', 'source_id', 'exam_id'] as $key) {
            if (! isset($filters[$key])) {
                continue;
            }

            $must[] = is_array($filters[$key])
                ? ['key' => $key, 'match' => ['any' => $filters[$key]]]
                : ['key' => $key, 'match' => ['value' => $filters[$key]]];
        }

        if (! empty($filters['locales'])) {
            $must[] = ['key' => 'locale', 'match' => ['any' => array_values($filters['locales'])]];
        }

        return $must === [] ? null : ['must' => $must];
    }
}
