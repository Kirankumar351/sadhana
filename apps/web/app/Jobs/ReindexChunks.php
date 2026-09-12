<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiChunk;
use App\Services\AI\ChunkBuilder;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\VectorStore;
use App\Services\AI\QdrantStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Rebuild and re-embed the corpus for one owner record.
 *
 * Fired by observers on every model that owns retrievable facts. The whole point is that a
 * correction made once in the admin panel reaches every AI answer within a minute, without
 * anyone remembering to run anything.
 *
 * Deliberately queued on `low`: reindexing is never more urgent than a push notification
 * or a page render, and on notification day the queue should be shedding this work, not
 * competing with it.
 */
class ReindexChunks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly string $modelClass,
        public readonly int $modelId,
        public readonly bool $deleted = false,
    ) {
        $this->onQueue('low');
    }

    public static function for(Model $record, bool $deleted = false): self
    {
        return new self($record::class, (int) $record->getKey(), $deleted);
    }

    /**
     * One reindex at a time per record. Two concurrent runs would race on
     * updateOrCreate and could leave a duplicated or half-deleted chunk set.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->modelClass.':'.$this->modelId))->dontRelease()];
    }

    public function handle(
        ChunkBuilder $builder,
        EmbeddingClient $embedder,
        VectorStore $vectors,
    ): void {
        /** @var class-string<Model> $class */
        $class = $this->modelClass;

        $record = $class::query()->withoutGlobalScopes()->find($this->modelId);

        /**
         * DELETION IS THE PATH THAT MATTERS MOST.
         *
         * If material is removed after a copyright complaint but its chunks stay in the
         * corpus, the assistant keeps quoting it and the takedown is incomplete in exactly
         * the way that has legal consequences. The chunks go first, then the vectors, and
         * a failure in either is reported rather than swallowed.
         */
        if ($this->deleted || $record === null) {
            $this->purge($builder, $vectors, $class);

            return;
        }

        $staleIds = $builder->rebuild($record);

        if ($staleIds === []) {
            return;
        }

        /**
         * Building chunks is free; embedding costs money and needs a provider.
         *
         * When AI is not configured — local development, CI, or a deployment where the key
         * has not been issued yet — the chunk text is still kept current and the rows are
         * left marked stale. `corpus:reindex --stale` picks them up once a key exists.
         *
         * The alternative, throwing, would mean a content editor cannot save a notification
         * because an unrelated subsystem is unconfigured. Publishing a notification must
         * never depend on the assistant being available.
         */
        if (blank(config('ai.anthropic.key'))) {
            return;
        }

        $this->embed($staleIds, $embedder, $vectors);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function purge(ChunkBuilder $builder, VectorStore $vectors, string $class): void
    {
        $sourceType = $builder->sourceTypeFor(new $class);

        if ($sourceType === null) {
            return;
        }

        /**
         * MySQL first, then the vector store.
         *
         * The order matters. `ai_chunks` is the owner of record and Retriever joins every
         * hit back to it, so removing the rows makes the material unservable immediately —
         * even if Qdrant is unreachable and its points linger. Doing it the other way
         * round would leave a window where a failed vector delete aborts the whole purge
         * and the material stays retrievable.
         */
        AiChunk::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $this->modelId)
            ->delete();

        $vectors->deleteBySource($sourceType, $this->modelId);
    }

    /**
     * @param  list<int>  $chunkIds
     */
    private function embed(array $chunkIds, EmbeddingClient $embedder, VectorStore $vectors): void
    {
        $chunks = AiChunk::query()->whereIn('id', $chunkIds)->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $vectorsById = $embedder->embedBatch($chunks->pluck('content')->all());

        $points = [];

        foreach ($chunks as $i => $chunk) {
            if (! isset($vectorsById[$i])) {
                continue;
            }

            $points[] = [$chunk->id, $vectorsById[$i], $chunk->metadata ?? []];
        }

        if ($vectors instanceof QdrantStore) {
            $vectors->upsertMany($points);
        } else {
            foreach ($points as [$id, $vector, $payload]) {
                $vectors->upsert($id, $vector, $payload);
            }
        }

        AiChunk::query()->whereIn('id', $chunks->pluck('id'))->update([
            'is_stale' => false,
            'embedding_model' => $embedder->model(),
            'embedded_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        // A stale corpus is a quiet failure: the assistant keeps answering, just from
        // older material. It has to be loud somewhere or nobody finds out for weeks.
        report($e);
    }
}
