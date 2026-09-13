<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScrapeSource;
use App\Notifications\ScraperBroken;
use App\Services\Ingestion\GenericListingScraper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Run one scrape source.
 *
 * On the `high` queue: being first to publish a breaking notification matters enormously
 * for search ranking, and a student who hears it on Telegram first has no reason to open
 * us. This is the one background job that should not wait behind anything.
 */
class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $sourceId)
    {
        $this->onQueue('high');
    }

    /**
     * One run per source at a time. Government sites are slow, and two overlapping runs
     * would double our request rate against a host that may already be rate-limiting us.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('scrape:'.$this->sourceId))->dontRelease()];
    }

    public function handle(GenericListingScraper $scraper): void
    {
        $source = ScrapeSource::find($this->sourceId);

        if ($source === null || ! $source->is_active) {
            return;
        }

        $result = $scraper->run($source);

        Log::info('scraper.run', [
            'source' => $source->name,
            'summary' => $result->summary(),
        ]);

        /**
         * Three consecutive failures is a layout change, not a blip.
         *
         * Government sites change without warning and that is normal — but it stops being
         * normal during notification season, and a scraper failing quietly through a
         * season is how we lose the ranking on every notification it would have caught.
         */
        if ($source->fresh()->consecutive_failures >= 3) {
            Notification::route('mail', config('app.ops_email'))
                ->notify(new ScraperBroken($source, $result));
        }
    }
}
