<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Telangana State Level Police Recruitment Board.
 *
 * tgprb.in (the old tslprb.in redirects here) is a React application whose HTML shell holds
 * no content at all. The recruitment cards are compiled into the JavaScript bundle, so that
 * is what gets read: each card's heading sits a few characters before its "Notification" PDF
 * link.
 *
 * This is the most fragile parser in the set, because a rebuild of their bundle can change
 * the minified shape. It fails loudly when the bundle cannot be found, which is the signal
 * that matters; a bundle with no notification cards is a quiet season, not an error.
 */
final class TgprbScraper extends BaseScraper
{
    public function fetchListing(ScrapeSource $source): Collection
    {
        $shell = $this->fetch($source->url);

        if (! preg_match('~src=["\'](/assets/index-[A-Za-z0-9_-]+\.js)["\']~', $shell, $m)) {
            throw new RuntimeException('TGPRB layout changed: the application bundle is no longer referenced.');
        }

        $bundle = $this->fetch($this->absolute($m[1], $source->url));

        preg_match_all(
            '~children:`([^`]{4,90})`\}\),\(0,\w+\.jsxs?\)\(`ul`.{0,300}?href:`(https://www\.tgprb\.in/[A-Za-z_]+/[^`]*Notification[^`]*\.pdf)`~s',
            $bundle,
            $cards,
            PREG_SET_ORDER,
        );

        return collect($cards)
            ->map(function (array $card) use ($source): ?array {
                $year = preg_match('~(20\d{2})~', $card[1].' '.$card[2], $y) ? (int) $y[1] : 0;

                if (! $this->isRecentYear($year)) {
                    return null;
                }

                // Their file names contain spaces ("FSL Notification.pdf"). Stored raw, the link
                // fails URL validation the moment a reviewer opens the draft to edit it.
                $url = $this->absolute($card[2], $source->url);
                $title = 'TGPRB – '.trim($card[1]);

                return [
                    'url' => $url,
                    'title' => $title,
                    // The card names a recruitment year, which is all there is to judge age by
                    // when the notification carries no date.
                    'year' => $year,
                    'data' => [
                        'title' => $title,
                        'organisation' => 'Telangana State Level Police Recruitment Board',
                        'notification_date' => preg_match('~dtd_(\d{2}-\d{2}-\d{4})~', $card[2], $d) ? $this->date($d[1]) : null,
                        'official_pdf_url' => $url,
                    ],
                ];
            })
            ->filter()
            ->unique('url')
            ->values();
    }
}
