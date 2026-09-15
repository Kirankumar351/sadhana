<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ExamNotification;
use App\Models\ScrapeSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
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

    public function __construct(
        protected readonly NotificationExtractor $extractor,
        protected readonly DocumentText $documents,
    ) {}

    /**
     * Listing items: at minimum a url and a title.
     *
     * An item may also carry:
     *   - `data`: facts read from the listing itself — a notification date, the official PDF,
     *     the apply link. They win over anything parsed from the detail page, because the
     *     listing states them explicitly.
     *   - `detail_url`: where the full notification lives, when that is not `url`.
     *   - `skip_detail`: the listing already holds everything (an API), so fetch nothing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    abstract public function fetchListing(ScrapeSource $source): Collection;

    /**
     * Parse the plain text of a detail page or PDF into notification fields.
     *
     * Return only what is genuinely READ from the page. A parser that guesses to fill the
     * shape is worse than one that returns nulls, because a null is visibly missing in the
     * review queue while a guess looks like a fact.
     *
     * @return array<string, mixed>
     */
    public function parseDetail(string $text, string $url): array
    {
        if ($text === '') {
            return [];
        }

        // The extractor's result first; facts the page states under a fixed label fill any
        // gap it left. A date written as "Last Date ... 22/08/2026" does not go missing
        // because a model chose to be cautious about it.
        return $this->extractor->extract($text, $url) + (new RuleBasedExtractor)->extract($text);
    }

    public function run(ScrapeSource $source): ScrapeResult
    {
        $found = 0;
        $created = 0;
        $skipped = 0;
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
                    [$data, $text] = $this->read($item);

                    // Closed, or too old to still be open. Correctly read, but nobody can
                    // apply, and a reviewer's time belongs to notifications that can still
                    // change someone's year.
                    if ($this->isClosed($data, $item)) {
                        $skipped++;

                        continue;
                    }

                    if (! $this->isUsable($data, $item)) {
                        $errors[] = "Too little parsed from {$item['url']}";

                        continue;
                    }

                    $this->createDraft($source, $item, $data, $text);
                    $created++;
                } catch (Throwable $e) {
                    // One bad detail page must not abort the whole run. On notification
                    // day the other items in the listing are the ones that matter.
                    $errors[] = $item['url'].': '.Str::limit($e->getMessage(), 200);
                    report($e);
                }
            }

            $source->update([
                'last_run_at' => now(),
                'last_success_at' => now(),
                'consecutive_failures' => 0,
            ]);

            return new ScrapeResult($found, $created, $errors, skipped: $skipped);
        } catch (Throwable $e) {
            $source->increment('consecutive_failures');
            $source->update(['last_run_at' => now()]);

            report($e);
            Log::warning('scraper.failed', ['source' => $source->name, 'error' => $e->getMessage()]);

            return new ScrapeResult($found, $created, [Str::limit($e->getMessage(), 300)], failed: true, skipped: $skipped);
        }
    }

    /**
     * Listing facts merged over whatever the detail page yields.
     *
     * @param  array<string, mixed>  $item
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function read(array $item): array
    {
        $listing = array_filter(
            (array) ($item['data'] ?? []),
            static fn ($value): bool => $value !== null && $value !== '',
        );

        if ($item['skip_detail'] ?? false) {
            return [$listing, (string) ($listing['description'] ?? '')];
        }

        $url = (string) ($item['detail_url'] ?? $item['url']);
        $response = $this->request($url);
        $text = $this->documents->from($response->body(), $response->header('Content-Type'), $url);

        $this->pause();

        return [array_merge($this->parseDetail($text, $url), $listing), $text];
    }

    protected function request(string $url): Response
    {
        return Http::withUserAgent(static::USER_AGENT)
            // Verification stays on. This only supplies intermediates that some board
            // servers leave out of their chain; see ScraperTls.
            ->withOptions(['verify' => app(ScraperTls::class)->verifyOption()])
            ->timeout(45)
            ->retry(2, 3000)
            ->get($url)
            ->throw();
    }

    protected function fetch(string $url): string
    {
        return $this->documents->utf8($this->request($url)->body());
    }

    protected function pause(): void
    {
        if (! app()->runningUnitTests()) {
            usleep(static::DELAY_MS * 1000);
        }
    }

    /**
     * Closed, or too old to still be open.
     *
     * A stated closing date settles it. Without one, a notification published more than six
     * months ago is out of date: recruitment windows here run 30 to 60 days, and last year's
     * RRB and TGPRB notices otherwise filled the review queue with drafts nobody could apply
     * for. A listing that gives only a past year, and no date at all, is treated the same.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $item
     */
    protected function isClosed(array $data, array $item = []): bool
    {
        $end = $data['apply_end_date'] ?? null;

        if (is_string($end)) {
            return CarbonImmutable::parse($end)->endOfDay()->isPast();
        }

        $published = $data['notification_date'] ?? null;

        if (is_string($published)) {
            return CarbonImmutable::parse($published)->addMonths(6)->isPast();
        }

        $year = (int) ($item['year'] ?? 0);

        return $year > 0 && $year < (int) now()->year;
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
            $data['apply_start_date'] ?? null,
            $data['total_vacancies'] ?? null,
            $data['min_qualification'] ?? null,
            $data['official_pdf_url'] ?? null,
            $data['apply_url'] ?? null,
            $data['registration_url'] ?? null,
        ]);

        return filled($item['title'] ?? null) && count($signal) >= 1;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $data
     */
    protected function createDraft(ScrapeSource $source, array $item, array $data, string $text = ''): ExamNotification
    {
        $title = Str::limit((string) ($data['title'] ?? $item['title']), 300, '');

        // The reviewer reads this beside the form when the original is not an embeddable
        // PDF. It is working material, rewritten before anything is published.
        $description = (string) ($data['description'] ?? Str::limit($text, 1500));

        unset($data['title'], $data['description']);

        return ExamNotification::create([
            ...$data,

            // English only at this point. Translation is a separate, reviewed step — a
            // machine-translated eligibility criterion must never reach a student.
            'title' => ['en' => $title],
            'description' => filled($description) ? ['en' => $description] : null,
            'slug' => $this->uniqueSlug($title),
            'organisation' => Str::limit((string) ($data['organisation'] ?? $source->name), 160, ''),
            'source_id' => $source->id,
            'source_url' => $item['url'],

            // The whole point. Never 'published'.
            'status' => 'pending_review',
        ]);
    }

    protected function absolute(string $href, string $base): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));

        if (preg_match('#^https?://#i', $href)) {
            return str_replace(' ', '%20', $href);
        }

        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($href, '/')) {
            return str_replace(' ', '%20', $root.$href);
        }

        // Relative to the listing page's own directory, the way a browser resolves it.
        $path = (string) ($parts['path'] ?? '/');
        $directory = str_ends_with($path, '/') ? rtrim($path, '/') : rtrim(str_replace('\\', '/', dirname($path)), '/');

        return str_replace(' ', '%20', $root.$directory.'/'.$href);
    }

    protected function cleanText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Day-first date parsing, shared with the rule-based extractor. */
    protected function date(string $raw): ?string
    {
        return (new RuleBasedExtractor)->date($raw);
    }

    /**
     * Boards publish titles in capitals. Title case reads faster in a feed on a small screen.
     */
    protected function titleCase(string $text): string
    {
        return mb_strtoupper($text) === $text ? Str::title(mb_strtolower($text)) : $text;
    }

    /**
     * Anything published before last year is an archive entry, not a job someone can apply for.
     */
    protected function isRecentYear(int $year): bool
    {
        return $year >= (int) now()->year - 1;
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 150, '')) ?: 'notification';
        $slug = $base;
        $i = 2;

        while (ExamNotification::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
