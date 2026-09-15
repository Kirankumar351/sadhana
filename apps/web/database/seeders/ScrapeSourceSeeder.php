<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ScrapeSource;
use App\Services\Ingestion\GenericListingScraper;
use App\Services\Ingestion\Scrapers\AppscScraper;
use App\Services\Ingestion\Scrapers\IbpsScraper;
use App\Services\Ingestion\Scrapers\RrbScraper;
use App\Services\Ingestion\Scrapers\SscApiScraper;
use App\Services\Ingestion\Scrapers\TgprbScraper;
use App\Services\Ingestion\Scrapers\TgpscScraper;
use Illuminate\Database\Seeder;

/**
 * The Phase 1 source list, from Vol 2 Chapter 11.4 — pointed at where each board actually
 * publishes, verified against the live sites in September 2026.
 *
 * The original URLs were guesses and most were wrong in a way that looked fine: TGPSC's and
 * APPSC's notification paths returned 404, SSC and TGPRB are JavaScript applications whose
 * HTML contains no links, and APPSC's home page is a gateway of four buttons. Every one of
 * them "ran" and found nothing.
 *
 * Frequency is set per source by how often the page actually changes AND by how much the
 * traffic matters. TGPSC and APPSC are checked every 15 minutes because they are the
 * highest-volume boards in this market and being first to publish wins the search ranking.
 * A careers page that posts twice a year is checked twice a day.
 *
 * ALWAYS KEEP A MANUAL ENTRY PATH. If a scraper breaks on notification day, a person must
 * be able to publish through the admin panel in five minutes. The scrapers save typing;
 * they are never the only way in.
 */
class ScrapeSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['TGPSC', 'https://websitenew.tgpsc.gov.in/directRecruitment', TgpscScraper::class, 15],
            ['APPSC', 'https://portal-psc.ap.gov.in/HomePages/RecruitmentNotifications.aspx', AppscScraper::class, 15],
            ['SSC', 'https://ssc.gov.in/api/admin/5.1/liveExams', SscApiScraper::class, 30],
            ['IBPS', 'https://www.ibps.in/', IbpsScraper::class, 60],
            ['RRB Secunderabad', 'https://rrbsecunderabad.gov.in/employment-notice', RrbScraper::class, 60],
            ['TS Police Recruitment Board', 'https://www.tgprb.in/', TgprbScraper::class, 120],
            ['Singareni Collieries', 'https://scclmines.com/scclnew/careers_Notification.asp', GenericListingScraper::class, 720],
            ['TGSPDCL', 'https://tgsouthernpower.org/careers', GenericListingScraper::class, 720],
            // A job-search portal for private employers, rendered entirely in JavaScript.
            // Kept for the record; not a source of government notifications.
            ['National Career Service', 'https://www.ncs.gov.in/', GenericListingScraper::class, 1440],
        ];

        foreach ($sources as [$name, $url, $parser, $frequency]) {
            $source = ScrapeSource::firstOrNew(['name' => $name]);

            $source->fill([
                'url' => $url,
                'parser_class' => $parser,
                'frequency_min' => $frequency,
            ]);

            // A NEW source is seeded inactive: pointing a crawler at a government site should
            // be a deliberate decision by someone ready to watch the review queue. An existing
            // source keeps whatever an admin set — re-running a seeder must never silently
            // switch ingestion off in production.
            if (! $source->exists) {
                $source->is_active = false;
            }

            $source->save();
        }

        $this->command?->info('Seeded '.count($sources).' scrape sources. New ones start inactive; enable them in the admin panel.');
    }
}
