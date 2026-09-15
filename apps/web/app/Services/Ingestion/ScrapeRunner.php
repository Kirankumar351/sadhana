<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ScrapeSource;
use App\Notifications\ScraperBroken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Runs one source with the scraper its row names.
 *
 * One entry point for the queued job, the console command and the admin "Pull now" button,
 * so all three record the same summary and raise the same alert.
 *
 * THE PARSER CLASS IS CHECKED AGAINST AN ALLOWLIST. It comes from a database row an admin
 * can edit, and resolving an arbitrary class name out of the container from a form field is
 * how an edit screen turns into code execution.
 */
final class ScrapeRunner
{
    public function run(ScrapeSource $source): ScrapeResult
    {
        $class = (string) $source->parser_class;

        if (! array_key_exists($class, ScrapeSource::PARSERS) || ! is_subclass_of($class, BaseScraper::class)) {
            $result = new ScrapeResult(errors: ["Unknown parser class: {$class}"], failed: true);

            $source->increment('consecutive_failures');
            $source->update(['last_run_at' => now()]);
        } else {
            /** @var BaseScraper $scraper */
            $scraper = app($class);
            $result = $scraper->run($source);
        }

        $source->update(['last_summary' => Str::limit($result->summary(), 500, '')]);

        Log::info('scraper.run', ['source' => $source->name, 'summary' => $result->summary()]);

        /**
         * Three consecutive failures is a layout change, not a blip.
         *
         * Government sites change without warning and that is normal — but it stops being
         * normal during notification season, and a scraper failing quietly through a
         * season is how we lose the ranking on every notification it would have caught.
         */
        if ((int) $source->fresh()?->consecutive_failures >= 3 && filled(config('app.ops_email'))) {
            Notification::route('mail', config('app.ops_email'))
                ->notify(new ScraperBroken($source, $result));
        }

        return $result;
    }
}
