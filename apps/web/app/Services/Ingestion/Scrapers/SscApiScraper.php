<?php

declare(strict_types=1);

namespace App\Services\Ingestion\Scrapers;

use App\Models\ScrapeSource;
use App\Services\Ingestion\BaseScraper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Staff Selection Commission.
 *
 * ssc.gov.in is an Angular application: the HTML a crawler receives contains no links at
 * all, which is why the generic scraper found nothing. The application itself reads its live
 * examinations from a public JSON endpoint, and that endpoint is the most reliable source in
 * the whole list — structured dates, fee and age limits, with no text to interpret.
 *
 * No detail page is fetched. Everything a draft needs is already in the response.
 */
final class SscApiScraper extends BaseScraper
{
    /** Registration and applications both happen on the portal itself. */
    public const PORTAL_URL = 'https://ssc.gov.in/';

    public function fetchListing(ScrapeSource $source): Collection
    {
        $exams = data_get($this->request($source->url)->json(), 'data');

        if (! is_array($exams)) {
            throw new RuntimeException('SSC API changed: the response no longer carries a "data" list.');
        }

        return collect($exams)
            ->filter(fn (mixed $exam): bool => is_array($exam)
                && ($exam['isActive'] ?? false)
                && filled($exam['id'] ?? null)
                && filled($exam['examName'] ?? null)
                && $this->isRecentYear((int) ($exam['examYear'] ?? 0)))
            ->map(fn (array $exam): array => $this->item($exam))
            ->unique('url')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $exam
     * @return array<string, mixed>
     */
    private function item(array $exam): array
    {
        $title = trim((string) $exam['examName']);
        $start = $this->istDate($exam['applicationStartDate'] ?? null);
        $end = $this->istDate($exam['applicationEndDate'] ?? null);
        $fee = is_numeric($exam['fee'] ?? null) && (int) $exam['fee'] > 0 ? (int) $exam['fee'] : null;
        $minAge = $this->age($exam['minAge'] ?? null);
        $maxAge = $this->age($exam['maxAge'] ?? null);

        return [
            // The API has no per-exam page, so the portal address carries the exam id. It is
            // what makes re-runs dedupe, and it still opens the official site for a reviewer.
            'url' => self::PORTAL_URL.'?exam='.rawurlencode((string) $exam['id']),
            'title' => $title,
            'skip_detail' => true,
            'data' => [
                'title' => $title,
                'organisation' => 'Staff Selection Commission',
                'apply_start_date' => $start,
                'apply_end_date' => $end,
                'fee_payment_end_date' => $this->istDate($exam['lastDateForFee'] ?? null),
                'exam_date' => $this->istDate($exam['examDate'] ?? null),
                'min_age' => $minAge,
                'max_age' => ($minAge !== null && $maxAge !== null && $maxAge < $minAge) ? null : $maxAge,
                // Only the general fee is published as a field. Exemptions are stated in the
                // notification, and inventing them here would tell people they owe nothing.
                'application_fee' => $fee !== null ? ['general' => $fee] : null,
                'apply_url' => self::PORTAL_URL,
                'registration_url' => self::PORTAL_URL,
                'description' => implode(' ', array_filter([
                    trim((string) ($exam['examDescription'] ?? '')),
                    $start && $end ? "Applications {$start} to {$end} (SSC live examinations API)." : null,
                    $fee !== null ? "General fee Rs. {$fee}." : null,
                    $minAge !== null && $maxAge !== null ? "Age {$minAge} to {$maxAge} years." : null,
                ])),
            ],
        ];
    }

    /**
     * Deadlines arrive in UTC: "2026-09-30T17:30:00.000Z" is 11 PM on the 30th in India.
     *
     * Reading only the date part happens to work for that one, and is a day early for any
     * deadline that falls after midnight IST. Converting first is the only version that is
     * right for every value the API can send.
     */
    private function istDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = str_contains($value, 'T')
                ? CarbonImmutable::parse($value)->setTimezone('Asia/Kolkata')
                : CarbonImmutable::parse($value, 'Asia/Kolkata');
        } catch (Throwable) {
            return null;
        }

        return $date->year >= 2020 ? $date->toDateString() : null;
    }

    private function age(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 14 && (int) $value <= 70 ? (int) $value : null;
    }
}
