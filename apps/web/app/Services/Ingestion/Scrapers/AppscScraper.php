<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Andhra Pradesh Public Service Commission.
 *
 * psc.ap.gov.in itself is only a gateway of four buttons. The notifications live on the
 * portal's Recruitment Notifications page, one link per notification, with the number and
 * date in the link text: "Notification No.29/2025, Dated:24/09/2025 - Notification to the
 * Post of Welfare Organiser ...". The PDF behind it states the application window.
 *
 * Applying is a two-step process on two different hosts — One-Time Profile Registration,
 * then the application itself — so a draft carries both links rather than one that only
 * works for candidates who have already registered.
 */
final class AppscScraper extends BaseScraper
{
    public const APPLY_URL = 'https://applications-psc.ap.gov.in/';

    public const REGISTRATION_URL = 'https://otpr-psc.ap.gov.in/';

    private const ROW = '~^Notification\s*No\s*\.?\s*(\d{1,3})\s*/\s*(\d{4})\s*,?\s*(?:Dated?|Dt)\s*[.:,]?\s*(\d{1,2}[./-]\d{1,2}[./-]\d{4})\s*[-–]?\s*(.+)$~iu';

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = $this->fetch($source->url);

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER);

        $rows = collect($links)
            ->map(fn (array $link): array => ['href' => trim($link[1]), 'text' => $this->cleanText($link[2])])
            ->filter(fn (array $link): bool => (bool) preg_match(self::ROW, $link['text']));

        if ($rows->isEmpty()) {
            throw new RuntimeException('APPSC layout changed: no "Notification No." rows found.');
        }

        return $rows
            ->map(function (array $link) use ($source): ?array {
                preg_match(self::ROW, $link['text'], $p);

                // Older notifications link to a breakup page rather than a PDF; they are also
                // years closed, so the year filter removes them before that matters.
                if (! $this->isRecentYear((int) $p[2]) || ! preg_match('~\.pdf$~i', $link['href'])) {
                    return null;
                }

                $url = $this->absolute($link['href'], $source->url);
                $post = preg_replace('~^Notification\s+(?:to|for)\s+the\s+~i', '', trim($p[4])) ?? trim($p[4]);
                $title = "APPSC Notification No. {$p[1]}/{$p[2]} – ".ucfirst($post);

                return [
                    'url' => $url,
                    'title' => $title,
                    'data' => [
                        'title' => $title,
                        'organisation' => 'Andhra Pradesh Public Service Commission',
                        'notification_date' => $this->date($p[3]),
                        'official_pdf_url' => $url,
                        'apply_url' => self::APPLY_URL,
                        'registration_url' => self::REGISTRATION_URL,
                    ],
                ];
            })
            ->filter()
            ->unique('url')
            ->take(25)
            ->values();
    }
}
