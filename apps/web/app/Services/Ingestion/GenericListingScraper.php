<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ScrapeSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A scraper for the shape most smaller recruitment pages actually have: a list of links,
 * often straight to a PDF, with the notification title as the link text.
 *
 * Deliberately unambitious. It finds candidate links and hands each detail page or PDF to
 * the extractor rather than parsing tables that differ on every site. The boards that carry
 * real traffic have their own scrapers in this namespace; this one covers careers pages
 * that post a handful of notices a year.
 */
class GenericListingScraper extends BaseScraper
{
    /** Link text containing one of these is probably a recruitment notification. */
    protected const KEYWORDS = [
        'recruitment', 'notification', 'vacancy', 'vacancies', 'advertisement',
        'apply online', 'posts', 'appointment', 'direct recruitment',
    ];

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = $this->fetch($source->url);

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $m): array => [
                'url' => $this->absolute($m[1], $source->url),
                'title' => $this->cleanText($m[2]),
            ])
            ->filter(fn (array $item): bool => $this->looksLikeNotification($item['title']))
            ->unique('url')
            // A listing page can carry hundreds of links. Cap the run so a layout change
            // cannot turn one scheduled scrape into a thousand requests at a government
            // site that is already slow.
            ->take(25)
            ->values();
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
}
