<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScrapeSource;
use App\Services\Ingestion\ScrapeRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Run one scrape source.
 *
 * On the `high` queue: being first to publish a breaking notification matters enormously
 * for search ranking, and a student who hears it on Telegram first has no reason to open
 * us. This is the one background job that should not wait behind anything.
 *
 * The scraper is the one the source row names. This job used to type-hint the generic
 * scraper, so `parser_class` was stored, shown in the admin panel, and never used — every
 * board was read with the one parser that cannot read any of the boards that matter.
 */
class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

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

    public function handle(ScrapeRunner $runner): void
    {
        $source = ScrapeSource::find($this->sourceId);

        if ($source === null || ! $source->is_active) {
            return;
        }

        $runner->run($source);
    }
}
