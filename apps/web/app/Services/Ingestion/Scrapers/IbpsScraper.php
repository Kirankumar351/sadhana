<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Institute of Banking Personnel Selection.
 *
 * IBPS runs recruitment for banks and financial institutions, and the home page links each
 * open one straight to its registration portal: "IOB Recruitment of Security Guards
 * Registration From 25-Aug-2026" -> ibpsreg.ibps.in/iobsgaug26/. That portal page states
 * "Commencement of online registration of application 25/08/2026" and "Closure of
 * registration of application 14/09/2026", which the rule-based extractor reads directly.
 *
 * The registration page is both the apply link and the registration link: IBPS has no
 * separate account step.
 */
final class IbpsScraper extends BaseScraper
{
    private const REGISTRATION_HOST = 'ibpsreg.ibps.in';

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = $this->fetch($source->url);

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER);

        return collect($links)
            ->map(function (array $link) use ($source): ?array {
                $text = $this->cleanText($link[2]);
                $url = $this->absolute($link[1], $source->url);
                $isRegistration = parse_url($url, PHP_URL_HOST) === self::REGISTRATION_HOST;

                if (Str::length($text) < 12 || ! ($isRegistration || preg_match('~^Apply Online for~i', $text))) {
                    return null;
                }

                $start = preg_match('~Registration\s+From\s+(.+)$~i', $text, $m) ? $this->date(trim($m[1])) : null;
                $title = trim(preg_replace(['~\s*Registration\s+From\s+.+$~i', '~^Apply Online for\s+~i'], '', $text) ?? $text);

                return [
                    'url' => $url,
                    'title' => $title,
                    'data' => [
                        'title' => $title,
                        'organisation' => $this->organisation($title),
                        'job_type' => 'psu',
                        'apply_start_date' => $start,
                        'apply_url' => $url,
                        'registration_url' => $isRegistration ? $url : null,
                    ],
                ];
            })
            ->filter()
            ->unique('url')
            ->take(25)
            ->values();
    }

    /**
     * "IOB Recruitment of ..." is recruitment FOR Indian Overseas Bank, conducted BY IBPS.
     * A student searching for the bank should find it under the bank's name.
     */
    private function organisation(string $title): string
    {
        return preg_match('~^([A-Z][A-Za-z&]{1,14})\s+(?:Direct\s+)?Recruitment\b~', $title, $m)
            ? "{$m[1]} (conducted by IBPS)"
            : 'Institute of Banking Personnel Selection';
    }
}
