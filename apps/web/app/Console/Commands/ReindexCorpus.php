<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReindexChunks;
use App\Models\AiChunk;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\ExamNotification;
use App\Models\Material;
use App\Models\NewsItem;
use App\Services\AI\Contracts\EmbeddingClient;
use Illuminate\Console\Command;

/**
 * Rebuild the retrieval corpus.
 *
 * Three jobs, deliberately separate:
 *
 *   --all       backfill everything. Run once, when the corpus is first built.
 *   --stale     re-embed only chunks marked stale. This is the nightly job.
 *   --drifted   find chunks embedded with a DIFFERENT model and re-embed them.
 *
 * That last one is the reason `embedding_model` is stored per chunk. Vectors from two
 * different models are not comparable, so changing the embedding model silently
 * invalidates the corpus — retrieval quality degrades for months with nothing failing and
 * no error anywhere. Being able to find the affected chunks is the whole defence.
 *
 * DO NOT RUN --all BEFORE THE CONTENT EXISTS. An assistant with nothing to retrieve
 * answers anyway, fluently and emptily, and the people who try it in that state do not
 * come back to try it again.
 */
class ReindexCorpus extends Command
{
    protected $signature = 'corpus:reindex
        {--all : Rebuild every chunk from every owner table}
        {--stale : Re-embed only chunks already marked stale}
        {--drifted : Re-embed chunks built with a different embedding model}
        {--type= : Restrict to one source type}';

    protected $description = 'Rebuild and re-embed the AI retrieval corpus';

    public function handle(EmbeddingClient $embedder): int
    {
        return match (true) {
            (bool) $this->option('all') => $this->backfill(),
            (bool) $this->option('drifted') => $this->drifted($embedder),
            (bool) $this->option('stale') => $this->stale(),
            default => $this->summary($embedder),
        };
    }

    /**
     * Status, when run with no options. Cheap, and the most common thing anyone wants.
     */
    private function summary(EmbeddingClient $embedder): int
    {
        $total = AiChunk::query()->count();
        $stale = AiChunk::query()->where('is_stale', true)->count();

        $drifted = AiChunk::query()
            ->whereNotNull('embedding_model')
            ->where('embedding_model', '!=', $embedder->model())
            ->count();

        $this->table(['', 'chunks'], [
            ['total', $total],
            ['awaiting embedding', $stale],
            ['embedded with an older model', $drifted],
        ]);

        $byType = AiChunk::query()
            ->selectRaw('source_type, source_locale, count(*) as c')
            ->groupBy('source_type', 'source_locale')
            ->get();

        if ($byType->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['source', 'locale', 'chunks'],
                $byType->map(fn ($r) => [$r->source_type, $r->source_locale, $r->c])->all(),
            );
        }

        if ($drifted > 0) {
            $this->warn("{$drifted} chunks were embedded with a different model. Retrieval across them is unreliable — run --drifted.");
        }

        return self::SUCCESS;
    }

    private function backfill(): int
    {
        $models = [
            'notification' => ExamNotification::query()->where('status', 'published'),
            'exam' => Exam::query()->where('is_active', true),
            'material' => Material::query()->where('status', 'published'),
            'news_item' => NewsItem::query()->where('status', 'published'),
            // Only best answers. An unvetted answer is a stranger's opinion, and the point
            // of grounding is that the source is trustworthy.
            'answer' => Answer::query()->where('is_best', true)->where('is_ai', false),
        ];

        if ($type = $this->option('type')) {
            $models = array_intersect_key($models, [$type => true]);
        }

        $queued = 0;

        foreach ($models as $label => $query) {
            $count = (clone $query)->count();

            if ($count === 0) {
                $this->line("  {$label}: nothing to index");

                continue;
            }

            $bar = $this->output->createProgressBar($count);
            $bar->setFormat("  {$label}: %current%/%max% [%bar%]");

            $query->chunkById(200, function ($records) use (&$queued, $bar): void {
                foreach ($records as $record) {
                    ReindexChunks::dispatch($record::class, (int) $record->getKey());
                    $queued++;
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        }

        $this->info("Queued {$queued} records for reindexing. Run a queue worker to process them.");

        return self::SUCCESS;
    }

    private function stale(): int
    {
        $groups = AiChunk::query()
            ->where('is_stale', true)
            ->select('source_type', 'source_id')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $class = $this->classFor($group->source_type);

            if ($class !== null) {
                ReindexChunks::dispatch($class, (int) $group->source_id);
            }
        }

        $this->info("Queued {$groups->count()} records whose chunks are stale.");

        return self::SUCCESS;
    }

    private function drifted(EmbeddingClient $embedder): int
    {
        $affected = AiChunk::query()
            ->whereNotNull('embedding_model')
            ->where('embedding_model', '!=', $embedder->model())
            ->update(['is_stale' => true]);

        $this->warn("Marked {$affected} chunks stale — they were embedded with a different model.");

        return $this->stale();
    }

    /**
     * @return class-string|null
     */
    private function classFor(string $sourceType): ?string
    {
        return match ($sourceType) {
            'notification' => ExamNotification::class,
            'exam' => Exam::class,
            'material' => Material::class,
            'news_item' => NewsItem::class,
            'answer' => Answer::class,
            default => null,
        };
    }
}
