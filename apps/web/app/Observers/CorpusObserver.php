<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ReindexChunks;
use App\Models\Exam;
use App\Models\ExamNotification;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps the retrieval corpus in step with the tables that own the facts.
 *
 * One observer for every corpus-bearing model, because the rule is the same for all of
 * them and writing it five times is how four of them drift.
 *
 * THE DELETE HOOK IS THE ONE PEOPLE FORGET. If material is removed after a copyright
 * complaint but its chunks stay in the corpus, the assistant keeps quoting it — and the
 * takedown is then incomplete in exactly the way that matters legally.
 */
class CorpusObserver
{
    public function saved(Model $model): void
    {
        $this->reindex($model);
        $this->purgeCache($model);
    }

    public function deleted(Model $model): void
    {
        ReindexChunks::dispatch($model::class, (int) $model->getKey(), deleted: true);
        $this->purgeCache($model);
    }

    /**
     * A restored record rejoins the corpus. Without this, un-deleting something in the
     * admin panel would bring the page back while leaving the assistant blind to it.
     */
    public function restored(Model $model): void
    {
        $this->reindex($model);
        $this->purgeCache($model);
    }

    private function reindex(Model $model): void
    {
        /**
         * Only published, visible records belong in the corpus.
         *
         * A draft notification is by definition unverified, and grounding an answer in
         * unverified eligibility criteria is precisely what the human review step exists
         * to prevent. If the status says it is not live, the chunks go.
         */
        $status = $model->getAttribute('status');

        $shouldIndex = $status === null
            || in_array($status, ['published', 'approved'], true);

        ReindexChunks::dispatch($model::class, (int) $model->getKey(), deleted: ! $shouldIndex);
    }

    /**
     * Forget the rendered page in every locale.
     *
     * Every locale, not just the current one: an editor correcting a date while the admin
     * panel happens to be in English would otherwise leave the Telugu page stale — and
     * Telugu is the version most of our users read.
     */
    private function purgeCache(Model $model): void
    {
        $slug = $model->getAttribute('slug');

        if ($slug === null) {
            return;
        }

        $prefix = match (true) {
            $model instanceof Exam => 'exam',
            $model instanceof ExamNotification => 'notification',
            default => null,
        };

        if ($prefix === null) {
            return;
        }

        foreach (Locale::allCacheKeys("{$prefix}:{$slug}") as $key) {
            Cache::forget($key);
        }

        Cache::forget('feed:stats');

        // TODO: dispatch PurgeCloudflareUrl here once the CDN is in front of the app.
        // Until then the edge holds nothing, so forgetting the application cache is the
        // whole of the invalidation.
    }
}
