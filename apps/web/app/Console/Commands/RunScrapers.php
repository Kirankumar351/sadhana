<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Models\ScrapeSource;
use Illuminate\Console\Command;

/**
 * Dispatch every scrape source that is due.
 *
 * Scheduled every 30 minutes, but each source carries its own frequency: TGPSC and APPSC
 * are checked every 15 minutes because they are the highest-traffic boards in our market,
 * while district collectorate pages are checked daily. Hammering a slow government site
 * every fifteen minutes for content that changes twice a year is how we get blocked.
 */
class RunScrapers extends Command
{
    protected $signature = 'scrape:run {--source= : Run one source by name} {--force : Ignore the frequency}';

    protected $description = 'Dispatch due scrape sources into the queue';

    public function handle(): int
    {
        $query = ScrapeSource::query()->where('is_active', true);

        if ($name = $this->option('source')) {
            $query->where('name', 'like', "%{$name}%");
        }

        $due = $query->get()->filter(fn (ScrapeSource $s): bool => $this->option('force') || $this->isDue($s));

        if ($due->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        foreach ($due as $source) {
            ScrapeSourceJob::dispatch($source->id);
            $this->line("  queued: {$source->name}");
        }

        $this->info("Dispatched {$due->count()} source(s).");

        return self::SUCCESS;
    }

    private function isDue(ScrapeSource $source): bool
    {
        if ($source->last_run_at === null) {
            return true;
        }

        return $source->last_run_at->addMinutes($source->frequency_min)->isPast();
    }
}
