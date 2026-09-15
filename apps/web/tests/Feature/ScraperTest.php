<?php

declare(strict_types=1);

use App\Models\ExamNotification;
use App\Models\ScrapeSource;
use App\Models\User;
use App\Services\Ingestion\Scrapers\AppscScraper;
use App\Services\Ingestion\Scrapers\RrbScraper;
use App\Services\Ingestion\Scrapers\SscApiScraper;
use App\Services\Ingestion\Scrapers\TgpscScraper;
use App\Services\Ingestion\ScraperTls;
use App\Services\Ingestion\ScrapeRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * The board scrapers, end to end against recorded page shapes.
 *
 * No test here touches a real government site: every request is faked, and a stray one
 * fails the test. The fixtures reproduce each board's actual markup closely enough that a
 * parser passing here parses the live page — which was verified by running them against the
 * live sites before these were written.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
});

function source(string $name, string $parser, string $url): ScrapeSource
{
    return ScrapeSource::create([
        'name' => $name,
        'url' => $url,
        'parser_class' => $parser,
        'frequency_min' => 15,
        'is_active' => true,
    ]);
}

function tgpscListing(): string
{
    $row = fn (string $href, string $text): string => "<tr><td><a href=\"{$href}\">{$text}</a></td></tr>";

    return '<html><body><h3>Direct Recruitment</h3><a href="/preview/HELP">Common mistakes made while bubbling OMR</a>'
        .'<div><a href="#">Current Notifications</a></div><table>'
        .$row('/preview/OPEN2026r95v', '06/G/TP/2026 - GENERAL RECRUITMENT TO THE POST OF TOWN PLANNING ASSISTANT IN TOWN AND COUNTRY PLANNING DEPARTMENT')
        .$row('/preview/CLOSED2026r95v', '05/G/TP/2026 - DIRECT RECRUITMENT FOR THE POST OF ASSISTANT DIRECTOR OF TOWN PLANNING')
        .$row('/preview/ADDENDUMr95v', 'Addendum')
        .$row('/preview/OLD2022r95v', '33/2022 - ASSISTANT PROFESSORS IN COLLEGIATE EDUCATION DEPARTMENT (GENERAL RECRUITMENT)')
        .'</table></body></html>';
}

function fakeTgpsc(): void
{
    Http::fake([
        'websitenew.tgpsc.gov.in/directRecruitment' => Http::response(tgpscListing(), 200, ['Content-Type' => 'text/html']),
        'websitenew.tgpsc.gov.in/preview/OPEN2026*' => Http::response(
            'NOTIFICATION NO. 06/G/TP/2026, DATED: 10/07/2026 Submission of Online Application From 15/07/2026 '
            .'Last Date & Time of submission of Online Application 22/08/2026 at 5:00 PM',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'websitenew.tgpsc.gov.in/preview/CLOSED2026*' => Http::response(
            'NOTIFICATION NO. 05/G/TP/2026, DATED: 16/07/2026 Submission of Online Application From 01/07/2026 '
            .'Last Date & Time of submission of Online Application 15/07/2026',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);
}

it('queues an open TGPSC notification with its PDF, dates and registration link', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    $result = app(ScrapeRunner::class)->run(
        source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment'),
    );

    $draft = ExamNotification::sole();

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($draft->status)->toBe('pending_review')
        ->and($draft->getTranslation('title', 'en'))->toContain('Town Planning Assistant')
        ->and($draft->official_pdf_url)->toBe('https://websitenew.tgpsc.gov.in/preview/OPEN2026r95v')
        ->and($draft->registration_url)->toBe(TgpscScraper::REGISTRATION_URL)
        ->and($draft->apply_start_date->toDateString())->toBe('2026-07-15')
        ->and($draft->apply_end_date->toDateString())->toBe('2026-08-22')
        ->and($draft->notification_date->toDateString())->toBe('2026-07-10');
});

it('never publishes what it pulls', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    app(ScrapeRunner::class)->run(source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment'));

    expect(ExamNotification::where('status', 'published')->count())->toBe(0);
});

it('ignores archive rows and addenda', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    $result = app(ScrapeRunner::class)->run(
        source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment'),
    );

    // Two 2026 rows found; the 2022 row and the addendum are not notifications to act on.
    expect($result->found)->toBe(2);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'OLD2022') || str_contains($request->url(), 'ADDENDUM'));
});

it('does not queue the same notification twice', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    $source = source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment');
    app(ScrapeRunner::class)->run($source);
    $second = app(ScrapeRunner::class)->run($source->fresh());

    expect($second->created)->toBe(0)
        ->and(ExamNotification::count())->toBe(1);
});

it('records what the run did on the source', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    $source = source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment');
    app(ScrapeRunner::class)->run($source);

    expect($source->fresh()->last_summary)->toBe('found 2, queued 1 for review, skipped 1 closed or out of date')
        ->and($source->fresh()->consecutive_failures)->toBe(0);
});

it('fails loudly when a board changes its layout', function (): void {
    Http::fake(['websitenew.tgpsc.gov.in/*' => Http::response('<html><body>Site under maintenance</body></html>')]);

    $source = source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment');
    $result = app(ScrapeRunner::class)->run($source);

    // An empty result here would look exactly like a quiet week. It must count as a failure,
    // because three of those in a row is what pages someone.
    expect($result->failed)->toBeTrue()
        ->and($source->fresh()->consecutive_failures)->toBe(1)
        ->and($source->fresh()->last_summary)->toContain('layout changed');
});

it('reads APPSC with both the registration and the application link', function (): void {
    $this->travelTo(CarbonImmutable::parse('2025-10-01'));

    Http::fake([
        'portal-psc.ap.gov.in/HomePages/RecruitmentNotifications.aspx' => Http::response(
            '<ul><li><a href="https://psc.ap.gov.in/Documents/NotificationDocuments/WelfareOrganiser_Notification_292025_24092025.pdf">'
            .'Notification No.29/2025 , Dated:24/09/2025 - Notification to the Post of Welfare Organiser in A.P. Sainik Welfare</a></li>'
            .'<li><a href="RecruitmentNotifications_Detailed_Breakup_Documents.aspx">Notification No.12/2023, Dated.08/12/2023 - Group-I Services</a></li></ul>',
        ),
        'psc.ap.gov.in/Documents/*' => Http::response(
            'Applications are invited for 10 vacancies within the maximum age of 45 years as on 01.07.2025. '
            .'The application submission window will be opened from 09/10/2025 to 29/10/2025 upto 11:00 PM.',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    app(ScrapeRunner::class)->run(
        source('APPSC', AppscScraper::class, 'https://portal-psc.ap.gov.in/HomePages/RecruitmentNotifications.aspx'),
    );

    $draft = ExamNotification::sole();

    expect($draft->apply_url)->toBe(AppscScraper::APPLY_URL)
        ->and($draft->registration_url)->toBe(AppscScraper::REGISTRATION_URL)
        ->and($draft->notification_date->toDateString())->toBe('2025-09-24')
        ->and($draft->apply_end_date->toDateString())->toBe('2025-10-29')
        ->and($draft->total_vacancies)->toBe(10)
        ->and($draft->max_age)->toBe(45);
});

it('reads the SSC API without fetching any page, converting deadlines to Indian time', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-12'));

    Http::fake([
        'ssc.gov.in/api/admin/5.1/liveExams' => Http::response(['statusCode' => '200', 'data' => [[
            'id' => 'ja7db5slcapf2026',
            'examYear' => '2026',
            'examName' => 'Sub-Inspector in Delhi Police and Central Armed Police Forces Examination - 2026',
            'applicationStartDate' => '2026-09-10',
            // 19:00 UTC on the 30th is 00:30 IST on 1 October. The date part alone is a day early.
            'applicationEndDate' => '2026-09-30T19:00:00.000Z',
            'lastDateForFee' => '2026-10-01T17:30:00.000Z',
            'fee' => '100',
            'minAge' => 18,
            'maxAge' => 30,
            'isActive' => true,
        ]]]),
    ]);

    app(ScrapeRunner::class)->run(source('SSC', SscApiScraper::class, 'https://ssc.gov.in/api/admin/5.1/liveExams'));

    $draft = ExamNotification::sole();

    expect($draft->apply_end_date->toDateString())->toBe('2026-10-01')
        ->and($draft->apply_start_date->toDateString())->toBe('2026-09-10')
        ->and($draft->min_age)->toBe(18)
        ->and($draft->max_age)->toBe(30)
        ->and($draft->application_fee)->toBe(['general' => 100])
        ->and($draft->apply_url)->toBe(SscApiScraper::PORTAL_URL);

    Http::assertSentCount(1);
});

it('refuses a parser class that is not on the allowlist', function (): void {
    // parser_class is editable in the admin panel. The container will build any class it is
    // handed, so an arbitrary name must never reach it.
    $source = source('Hostile', User::class, 'https://example.com/');

    $result = app(ScrapeRunner::class)->run($source);

    expect($result->failed)->toBeTrue()
        ->and($result->errors[0])->toContain('Unknown parser class')
        ->and($source->fresh()->consecutive_failures)->toBe(1);

    Http::assertNothingSent();
});

it('pulls from the command line and says what it found', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-20'));
    fakeTgpsc();

    source('TGPSC', TgpscScraper::class, 'https://websitenew.tgpsc.gov.in/directRecruitment');

    $this->artisan('scrape:run', ['--sync' => true, '--force' => true])
        ->expectsOutputToContain('queued 1 for review')
        ->assertSuccessful();
});

it('shows the registration and apply links on a published notification', function (): void {
    $notification = ExamNotification::create([
        'slug' => 'appsc-welfare-organiser-2025',
        'title' => ['en' => 'APPSC Welfare Organiser'],
        'organisation' => 'Andhra Pradesh Public Service Commission',
        'apply_end_date' => now()->addDays(20)->toDateString(),
        'registration_url' => AppscScraper::REGISTRATION_URL,
        'apply_url' => AppscScraper::APPLY_URL,
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get(route('notifications.show', ['locale' => 'en', 'slug' => $notification->slug]))
        ->assertOk()
        ->assertSee(AppscScraper::REGISTRATION_URL, false)
        ->assertSee(AppscScraper::APPLY_URL, false)
        ->assertSee('Register first');
});

it('reads only RRB recruitment notices, newest first, and not their follow-ups', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-20'));

    $card = fn (string $text, string $pdf, string $date): string => '<div class="content-text-right"><h4 class="mt-2 mb-1">'
        .$text.' <a href="https://rrbsecunderabad.gov.in/wp-content/uploads/'.$pdf.'">English</a> and '
        .'<a href="https://rrbsecunderabad.gov.in/wp-content/uploads/hindi-'.$pdf.'">Hindi</a></h4> <p>Date : '.$date.'</p></div>';

    Http::fake([
        'rrbsecunderabad.gov.in/employment-notice' => Http::response('<section>'
            .$card('CEN 01/2026 (ALP) - Detailed Centralized Employment Notification for Assistant Loco Pilot', 'cen-01-2026.pdf', '14/05/2026')
            .$card('CEN 09/2025 (Level-1) - Corrigendum-2 to notification for recruitment to Level 1', 'corr-09-2025.pdf', '16/02/2026')
            .$card('CEN 01/2019 (NTPC) - Interim Results Replacement Part-01 of Level 5', 'results.pdf', '17/01/2026')
            .'<!-- '.$card('CEN 03/2026 (JE) - Detailed Centralized Notification, withdrawn', 'withdrawn.pdf', '01/08/2026').' -->'
            .$card('CEN 04/2026 (JE & DMS) - Detailed Centralized Notification for the posts of Junior Engineer', 'cen-04-2026.pdf', '13/08/2026')
            .'</section>'),
        'rrbsecunderabad.gov.in/wp-content/uploads/cen-04-2026.pdf' => Http::response(
            'IMPORTANT DATES: Opening date of Online application 16-08-2026 Closing date for Submission of Online Application 15-09-2026 (23:59 hours)',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'rrbsecunderabad.gov.in/wp-content/uploads/cen-01-2026.pdf' => Http::response(
            'IMPORTANT DATES: Opening date of Online application 16-05-2026 Closing date for Submission of Online Application 14-06-2026',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $result = app(ScrapeRunner::class)->run(
        source('RRB Secunderabad', RrbScraper::class, 'https://rrbsecunderabad.gov.in/employment-notice'),
    );

    $draft = ExamNotification::sole();

    // Two recruitments. The corrigendum, the results and the commented-out card are not.
    expect($result->found)->toBe(2)
        ->and($result->skipped)->toBe(1)
        ->and($draft->getTranslation('title', 'en'))->toContain('Junior Engineer')
        ->and($draft->apply_start_date->toDateString())->toBe('2026-08-16')
        ->and($draft->apply_end_date->toDateString())->toBe('2026-09-15')
        ->and($draft->apply_url)->toBe(RrbScraper::APPLY_URL);

    // The page lists oldest first; the newest notice must be read first.
    $pdfs = collect(Http::recorded())
        ->map(fn (array $pair): string => $pair[0]->url())
        ->filter(fn (string $url): bool => str_contains($url, '/uploads/'))
        ->values();

    expect($pdfs->first())->toContain('cen-04-2026');
});

it('treats a notice published over six months ago with no closing date as out of date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-15'));

    Http::fake([
        'rrbsecunderabad.gov.in/employment-notice' => Http::response(
            '<h4>CEN 08/2025 (Isolated Categories) - Detailed Centralized Employment Notification '
            .'<a href="https://rrbsecunderabad.gov.in/wp-content/uploads/cen-08-2025.pdf">English</a></h4> <p>Date : 29/12/2025</p>',
        ),
        'rrbsecunderabad.gov.in/wp-content/uploads/*' => Http::response('A scanned notice with no text layer.', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = app(ScrapeRunner::class)->run(
        source('RRB Secunderabad', RrbScraper::class, 'https://rrbsecunderabad.gov.in/employment-notice'),
    );

    expect($result->created)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and(ExamNotification::count())->toBe(0);
});

it('keeps certificate verification on and supplies the intermediate APPSC leaves out', function (): void {
    $option = app(ScraperTls::class)->verifyOption();

    // Never false. Turning verification off would accept any certificate at all.
    expect($option)->not->toBeFalse();

    if (is_string($option)) {
        preg_match(
            '/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s',
            (string) file_get_contents(resource_path('certs/globalsign-gcc-r46-ev-tls-ca-2025.pem')),
            $pem,
        );

        expect((string) file_get_contents($option))->toContain($pem[0]);
    }
});

it('runs each source in its own process, so one crash cannot stop the rest', function (): void {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(errorOutput: 'PHP Fatal error:  Allowed memory size of 536870912 bytes exhausted', exitCode: 255))
            ->push(Process::result(output: '    found 3, queued 3 for review')),
    ]);

    $crashing = source('RRB Secunderabad', RrbScraper::class, 'https://rrbsecunderabad.gov.in/employment-notice');
    source('SSC', SscApiScraper::class, 'https://ssc.gov.in/api/admin/5.1/liveExams');

    $this->artisan('scrape:run', ['--sync' => true, '--force' => true])
        ->expectsOutputToContain('crashed: PHP Fatal error')
        ->expectsOutputToContain('queued 3 for review')
        ->assertSuccessful();

    expect($crashing->fresh()->consecutive_failures)->toBe(1)
        ->and($crashing->fresh()->last_summary)->toContain('crashed');

    Process::assertRanTimes(
        fn ($process): bool => in_array('scrape:run', (array) $process->command, true),
        2,
    );
});
