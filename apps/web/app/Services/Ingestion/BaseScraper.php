<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ExamNotification;
use App\Models\ScrapeSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base for every government-site scraper.
 *
 * NOTHING SCRAPED IS EVER PUBLISHED AUTOMATICALLY. Everything lands as
 * `pending_review` and a person verifies the dates, the eligibility and the fees against
 * the official PDF before it goes live. That is Decision D9 and it does not bend, however
 * good the extraction gets — a wrong last date costs a student a year.
 *
 * We are a guest on these sites. The crawler identifies itself honestly, respects a
 * conservative rate limit, and stores FACTS (dates, vacancy counts, eligibility) rather
 * than mirroring long documents. Every notification deep-links back to the official page
 * rather than replacing it.
 *
 * Government sites change layout without warning. That is normal, not an emergency — but
 * it becomes an emergency during notification season, which is why three consecutive
 * failures raise an alert rather than being logged and forgotten.
 */
abstract class BaseScraper
{
    protected const USER_AGENT = 'SadhanaBot/1.0 (+https://sadhana.study/bot)';

    /** Between requests to the same host. Being impolite gets us blocked. */
    protected const DELAY_MS = 1500;

    /**
     * Listing items: at minimum a url and a title.
     *
     * @return Collection<int, array{url: string, title: string, date?: string}>
     */
    abstract public function fetchListing(ScrapeSource $source): Collection;

    /**
     * Parse a detail page into notification fields.
     *
     * Return only what is genuinely READ from the page. A parser that guesses to fill the
     * shape is worse than one that returns nulls, because a null is visibly missing in the
     * review queue while a guess looks like a fact.
     *
     * @return array<string, mixed>
     */
    abstract public function parseDetail(string $html, string $url): array;

    public function run(ScrapeSource $source): ScrapeResult
    {
        $found = 0;
        $created = 0;
        $errors = [];

        try {
            foreach ($this->fetchListing($source) as $item) {
                $found++;

                // Dedupe on the source URL. Re-running a scraper must never create a
                // second copy of a notification the review queue already holds.
                if (ExamNotification::withTrashed()->where('source_url', $item['url'])->exists()) {
                    continue;
                }

                try {
                    $data = $this->parseDetail($this->fetch($item['url']), $item['url']);

                    if (! $this->isUsable($data, $item)) {
                        $errors[] = "Too little parsed from {$item['url']}";

                        continue;
                    }

                    $this->createDraft($source, $item, $data);
                    $created++;
                } catch (Throwable $e) {
                    // One bad detail page must not abort the whole run. On notification
                    // day the other items in the listing are the ones that matter.
                    $errors[] = $item['url'].': '.$e->getMessage();
                    report($e);
                }

                usleep(static::DELAY_MS * 1000);
            }

            $source->update([
                'last_run_at' => now(),
                'last_success_at' => now(),
                'consecutive_failures' => 0,
            ]);

            return new ScrapeResult($found, $created, $errors);
        } catch (Throwable $e) {
            $source->increment('consecutive_failures');
            $source->update(['last_run_at' => now()]);

            report($e);
            Log::warning('scraper.failed', ['source' => $source->name, 'error' => $e->getMessage()]);

            return new ScrapeResult($found, $created, [$e->getMessage()], failed: true);
        }
    }

    protected function fetch(string $url): string
    {
        return Http::withUserAgent(static::USER_AGENT)
            ->timeout(30)
            ->retry(2, 3000)
            ->get($url)
            ->throw()
            ->body();
    }

    /**
     * Is there enough here to be worth a reviewer's time?
     *
     * A row with nothing but a title wastes the queue and trains people to click through
     * without reading, which is the habit that eventually publishes a wrong date.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $item
     */
    protected function isUsable(array $data, array $item): bool
    {
        $signal = array_filter([
            $data['apply_end_date'] ?? null,
            $data['total_vacancies'] ?? null,
            $data['min_qualification'] ?? null,
            $data['official_pdf_url'] ?? null,
        ]);

        return filled($item['title'] ?? null) && count($signal) >= 1;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $data
     */
    protected function createDraft(ScrapeSource $source, array $item, array $data): ExamNotification
    {
        $title = (string) ($data['title'] ?? $item['title']);

        return ExamNotification::create([
            ...$data,

            // English only at this point. Translation is a separate, reviewed step — a
            // machine-translated eligibility criterion must never reach a student.
            'title' => ['en' => $title],
            'slug' => $this->uniqueSlug($title),
            'organisation' => $data['organisation'] ?? $source->name,
            'source_id' => $source->id,
            'source_url' => $item['url'],

            // The whole point. Never 'published'.
            'status' => 'pending_review',
        ]);
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 150, ''));
        $slug = $base;
        $i = 2;

        while (ExamNotification::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
