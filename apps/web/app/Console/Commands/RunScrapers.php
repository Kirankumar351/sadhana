<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Models\ScrapeSource;
use App\Services\Ingestion\ScrapeResult;
use App\Services\Ingestion\ScrapeRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Pull every scrape source that is due.
 *
 * Scheduled every 30 minutes, but each source carries its own frequency: TGPSC and APPSC
 * are checked every 15 minutes because they are the highest-traffic boards in our market,
 * while careers pages are checked twice a day. Hammering a slow government site every
 * fifteen minutes for content that changes twice a year is how we get blocked.
 *
 * `--sync` runs now and prints each result, for the first pull on a new machine and for
 * checking a parser after a board changes its site. With more than one source, each runs in
 * its own process: a PHP fatal error cannot be caught, and on the first live pull one RRB
 * notification exhausted the memory limit and killed the command before IBPS and every
 * source after it had run.
 */
class RunScrapers extends Command
{
    protected $signature = 'scrape:run
        {--source= : Run one source by name}
        {--id= : Run one source by id}
        {--force : Ignore the frequency}
        {--sync : Run now instead of queueing, and print each result}';

    protected $description = 'Pull new notifications from every due scrape source';

    public function handle(ScrapeRunner $runner): int
    {
        $query = ScrapeSource::query()->where('is_active', true);

        if ($id = $this->option('id')) {
            $query->whereKey((int) $id);
        } elseif ($name = $this->option('source')) {
            $query->where('name', 'like', "%{$name}%");
        }

        $due = $query->orderBy('id')->get()->filter(fn (ScrapeSource $s): bool => $this->option('force') || $this->isDue($s));

        if ($due->isEmpty()) {
            $this->info('Nothing due.');

            return self::SUCCESS;
        }

        if (! $this->option('sync')) {
            foreach ($due as $source) {
                ScrapeSourceJob::dispatch($source->id);
                $this->line("  queued: {$source->name}");
            }

            $this->info("Dispatched {$due->count()} source(s).");

            return self::SUCCESS;
        }

        if ($due->count() === 1) {
            return $this->runHere($runner, $due->first())->failed ? self::FAILURE : self::SUCCESS;
        }

        $failed = 0;

        foreach ($due as $source) {
            $this->line("  {$source->name} ...");
            $failed += $this->runIsolated($source) ? 0 : 1;
        }

        $this->info("Ran {$due->count()} sources. New drafts are in the admin Review Queue.");

        return $failed === $due->count() ? self::FAILURE : self::SUCCESS;
    }

    private function runHere(ScrapeRunner $runner, ScrapeSource $source): ScrapeResult
    {
        $result = $runner->run($source);

        $this->line('    '.$result->summary());

        foreach (array_slice($result->errors, 0, 3) as $error) {
            $this->warn('    - '.$error);
        }

        return $result;
    }

    /**
     * One source in a child process, so its crash costs only itself.
     *
     * A child that exits with FAILURE ran and recorded its own failure. Any other non-zero
     * exit means it died before it could — a fatal error — so the crash is recorded on the
     * source row here, where the admin screen will show it.
     */
    private function runIsolated(ScrapeSource $source): bool
    {
        $process = Process::timeout(900)->run([
            PHP_BINARY, base_path('artisan'), 'scrape:run', '--sync', '--force', '--id='.$source->id,
        ]);

        $output = trim($process->output());

        if ($process->successful()) {
            $this->line('    '.$output);

            return true;
        }

        if ($process->exitCode() === self::FAILURE) {
            $this->line('    '.$output);

            return false;
        }

        $detail = trim($process->errorOutput()) !== '' ? trim($process->errorOutput()) : $output;
        $reason = Str::limit(trim(Str::before($detail, "\n")) ?: 'no output', 250);

        $source->increment('consecutive_failures');
        $source->update(['last_run_at' => now(), 'last_summary' => "failed: crashed — {$reason}"]);

        $this->error("    crashed: {$reason}");

        return false;
    }

    private function isDue(ScrapeSource $source): bool
    {
        if ($source->last_run_at === null) {
            return true;
        }

        return $source->last_run_at->addMinutes($source->frequency_min)->isPast();
    }
}
