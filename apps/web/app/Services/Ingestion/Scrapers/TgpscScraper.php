<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Telangana Public Service Commission.
 *
 * The listing is the "Current Notifications" block on websitenew.tgpsc.gov.in/directRecruitment.
 * Each row is a notification number and a title linking to the notification PDF, and that PDF
 * is the only place the dates exist ("Submission of Online Application From 15/07/2026 ...
 * Last Date & Time of submission of Online Application 22/08/2026").
 *
 * The same block lists every notification back to 2017, so rows are filtered on the year in
 * the notification number. Addenda sit in the same rows as separate links and are ignored:
 * they amend a notification, they are not one.
 */
final class TgpscScraper extends BaseScraper
{
    /** One-Time Registration. Every TGPSC application starts from this account. */
    public const REGISTRATION_URL = 'https://otr.tgpsc.gov.in/login?type=new';

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = $this->fetch($source->url);
        $start = stripos($html, 'Current Notifications');

        if ($start === false) {
            // Not an empty listing: the page no longer looks the way this parser expects.
            // Failing loudly is what turns a silent layout change into an alert.
            throw new RuntimeException('TGPSC layout changed: the "Current Notifications" heading is gone.');
        }

        preg_match_all('/<a\s[^>]*href=["\']([^"\']*\/preview\/[^"\']+)["\'][^>]*>(.*?)<\/a>/is', substr($html, $start), $links, PREG_SET_ORDER);

        return collect($links)
            ->map(function (array $link) use ($source): ?array {
                $text = $this->cleanText($link[2]);

                if (! preg_match('~^(\d{1,3}/(?:[A-Z]{1,4}/){0,3}(\d{4}))\s*[-–]\s*(.+)$~u', $text, $p)
                    || ! $this->isRecentYear((int) $p[2])) {
                    return null;
                }

                $url = $this->absolute($link[1], $source->url);
                $title = "TGPSC Notification No. {$p[1]} – ".$this->titleCase($p[3]);

                return [
                    'url' => $url,
                    'title' => $title,
                    'data' => [
                        'title' => $title,
                        'organisation' => 'Telangana Public Service Commission',
                        'official_pdf_url' => $url,
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
