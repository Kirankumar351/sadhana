<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ScrapeSource;
use App\Services\Ingestion\GenericListingScraper;
use Illuminate\Database\Seeder;

/**
 * The Phase 1 source list, from Vol 2 Chapter 11.4.
 *
 * Frequency is set per source by how often the page actually changes AND by how much the
 * traffic matters. TGPSC and APPSC are checked every 15 minutes because they are the
 * highest-volume boards in this market and being first to publish wins the search ranking.
 * A district collectorate page is checked daily, because checking it every fifteen minutes
 * would be rude to a slow government server for content that changes twice a year.
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
            ['TGPSC', 'https://www.tgpsc.gov.in/notifications', 15],
            ['APPSC', 'https://psc.ap.gov.in/Home/Notifications', 15],
            ['TS Police Recruitment Board', 'https://www.tslprb.in/', 30],
            ['SSC', 'https://ssc.gov.in/', 30],
            ['RRB Secunderabad', 'https://rrbsecunderabad.gov.in/', 30],
            ['IBPS', 'https://www.ibps.in/', 60],
            ['National Career Service', 'https://www.ncs.gov.in/', 60],
            ['Singareni Collieries', 'https://scclmines.com/', 120],
            ['TGSPDCL', 'https://tgsouthernpower.org/', 120],
        ];

        foreach ($sources as [$name, $url, $frequency]) {
            ScrapeSource::updateOrCreate(
                ['name' => $name],
                [
                    'url' => $url,
                    'parser_class' => GenericListingScraper::class,
                    'frequency_min' => $frequency,
                    // Seeded INACTIVE. Pointing a crawler at nine government sites should
                    // be a deliberate decision taken once someone is ready to watch the
                    // review queue, not something that starts because a seeder ran.
                    'is_active' => false,
                ],
            );
        }

        $this->command?->info('Seeded '.count($sources).' scrape sources, all inactive. Enable them in the admin panel when someone is ready to review the queue.');
    }
}
