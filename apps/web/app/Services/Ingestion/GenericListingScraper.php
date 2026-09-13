<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ScrapeSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A scraper that works on the shape most Indian government recruitment pages actually have:
 * a list of links, often straight to a PDF, with the notification title as the link text.
 *
 * Deliberately unambitious. It finds candidate links and hands the detail page to the AI
 * extractor rather than trying to parse tables that differ on every site and change without
 * warning. A brittle bespoke parser per board is weeks of work that breaks every season;
 * this gets a reviewer a populated draft to check, which is all the pipeline needs.
 *
 * Where a board justifies it — TGPSC and APPSC carry the most traffic — a dedicated
 * subclass can override parseDetail with real selectors.
 */
class GenericListingScraper extends BaseScraper
{
    /** Link text containing one of these is probably a recruitment notification. */
    protected const KEYWORDS = [
        'recruitment', 'notification', 'vacancy', 'vacancies', 'advertisement',
        'apply online', 'posts', 'appointment', 'direct recruitment',
    ];

    public function __construct(
        protected readonly NotificationExtractor $extractor,
    ) {}

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = $this->fetch($source->url);

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $m) use ($source): array {
                $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));

                return ['url' => $this->absolute($m[1], $source->url), 'title' => $text];
            })
            ->filter(fn (array $item): bool => $this->looksLikeNotification($item['title']))
            ->unique('url')
            // A listing page can carry hundreds of links. Cap the run so a layout change
            // cannot turn one scheduled scrape into a thousand requests at a government
            // site that is already slow.
            ->take(25)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function parseDetail(string $html, string $url): array
    {
        $text = $this->readableText($html);

        // AI extraction, then human review. Never one without the other.
        return $this->extractor->extract($text, $url);
    }

    protected function looksLikeNotification(string $title): bool
    {
        if (Str::length($title) < 12) {
            return false;
        }

        $lower = Str::lower($title);

        foreach (static::KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function absolute(string $href, string $base): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $root.'/'.ltrim($href, '/');
    }

    protected function readableText(string $html): string
    {
        $html = preg_replace('/<(script|style|nav|footer)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        // Trimmed hard: the extractor is charged per token and a government page is mostly
        // navigation. The facts we need are always near the top.
        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? $text), 12000, '');
    }
}
