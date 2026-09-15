<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Railway Recruitment Board, Secunderabad.
 *
 * Employment notices are a stack of cards, each an <h4> naming the CEN with English and
 * Hindi PDF links, followed by a "Date : dd/mm/yyyy" line. The page also keeps about a
 * hundred withdrawn cards inside HTML comments. A browser never shows them, so neither does
 * this: they are stripped before parsing.
 *
 * MOST CARDS ARE NOT RECRUITMENTS. For every notification there are a dozen follow-ups —
 * corrigenda, document verification notices, interim results, cut-off marks. The first live
 * pull queued all of them. Only cards that announce a recruitment are read now.
 *
 * Every RRB now takes applications through one national portal, which serves as both the
 * apply and the registration link.
 */
final class RrbScraper extends BaseScraper
{
    public const APPLY_URL = 'https://www.rrbapply.gov.in/';

    /** A card announcing a recruitment. */
    private const NOTIFICATION = '~(?:detailed\s+centrali[sz]ed\s+(?:employment\s+)?notification|centrali[sz]ed\s+employment\s+notification|indicative\s+(?:notice|advertisement))~i';

    /** A card about a recruitment already under way: real, but nothing to apply for. */
    private const FOLLOW_UP = '~(?:result|cut[\s-]?off|document\s+verification|\bDV\b|corrigendum|addendum|reschedul|viewing\s+of\s+cbt|objection|answer\s+key|application\s+status|admit\s+card|mock\s+test|replacement|e-?call\s+letter)~i';

    public function fetchListing(ScrapeSource $source): Collection
    {
        $html = preg_replace('/<!--.*?-->/s', ' ', $this->fetch($source->url)) ?? '';

        preg_match_all('~<h4[^>]*>(.*?)</h4>\s*(?:<p[^>]*>\s*Date\s*:\s*(\d{1,2}/\d{1,2}/\d{4})\s*</p>)?~is', $html, $cards, PREG_SET_ORDER);

        $notices = collect($cards)->filter(fn (array $card): bool => (bool) preg_match('~\bCEN\b~i', strip_tags($card[1])));

        if ($notices->isEmpty()) {
            throw new RuntimeException('RRB Secunderabad layout changed: no CEN cards found.');
        }

        return $notices
            ->map(fn (array $card): ?array => $this->item($card, $source))
            ->filter()
            // Newest first. The page lists oldest first, and the cap below would otherwise
            // keep last year's notices and drop this month's.
            ->sortByDesc(fn (array $item): string => (string) ($item['data']['notification_date'] ?? ''))
            ->unique('url')
            ->take(25)
            ->values();
    }

    /**
     * @param  array<int, string>  $card
     * @return array<string, mixed>|null
     */
    private function item(array $card, ScrapeSource $source): ?array
    {
        $text = $this->cleanText($card[1]);

        if (! preg_match(self::NOTIFICATION, $text) || preg_match(self::FOLLOW_UP, $text)) {
            return null;
        }

        preg_match_all('~<a\s[^>]*href=["\']([^"\']+\.pdf)["\'][^>]*>(.*?)</a>~is', $card[1], $pdfs, PREG_SET_ORDER);

        $english = collect($pdfs)->first(
            fn (array $a): bool => stripos($this->cleanText($a[2]).' '.$a[1], 'eng') !== false,
        ) ?? ($pdfs[0] ?? null);

        if ($english === null) {
            return null;
        }

        $date = ($card[2] ?? '') !== '' ? $this->date($card[2]) : null;
        $year = $date !== null
            ? (int) substr($date, 0, 4)
            : (preg_match('~CEN\s*(?:No\.?)?\s*\d{1,2}\s*/\s*(\d{4})~i', $text, $y) ? (int) $y[1] : 0);

        if (! $this->isRecentYear($year)) {
            return null;
        }

        $url = $this->absolute($english[1], $source->url);
        $name = trim(preg_replace('~\s*(?:English|Hindi)(?:\s*(?:and|&)\s*(?:English|Hindi))?\.?\s*$~i', '', $text) ?? $text);
        $title = 'RRB Secunderabad – '.$name;

        return [
            'url' => $url,
            'title' => $title,
            'year' => $year,
            'data' => [
                'title' => $title,
                'organisation' => 'Railway Recruitment Board, Secunderabad',
                'notification_date' => $date,
                'official_pdf_url' => $url,
                'apply_url' => self::APPLY_URL,
                'registration_url' => self::APPLY_URL,
            ],
        ];
    }
}
