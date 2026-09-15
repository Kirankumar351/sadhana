<?php

declare(strict_types=1);

use App\Services\Ingestion\RuleBasedExtractor;

/**
 * Reading facts straight off a notification.
 *
 * Every fixture here is phrased the way the real board phrases it, copied from the live
 * documents in September 2026. When a board rewords its template, the matching test is the
 * one that should fail — loudly, here, rather than silently as a notification with no dates.
 */
beforeEach(function (): void {
    $this->rules = new RuleBasedExtractor;
});

it('reads a TGPSC chronology', function (): void {
    $text = 'TELANGANA PUBLIC SERVICE COMMISSION: HYDERABAD NOTIFICATION NO. 06/G/TP/2026, DATED: 10/07/2026 '
        .'GENERAL RECRUITMENT TO THE POST OF TOWN PLANNING ASSISTANT 1.2. Chronology: Important dates and time. '
        .'Submission of Online Application From 15/07/2026 Last Date & Time of submission of Online Application '
        .'22/08/2026 at 5:00 PM Downloading of Hall tickets 7 days prior to the examination.';

    expect($this->rules->extract($text))
        ->toMatchArray([
            'notification_date' => '2026-07-10',
            'apply_start_date' => '2026-07-15',
            'apply_end_date' => '2026-08-22',
        ]);
});

it('reads an APPSC application window, vacancies and age', function (): void {
    $text = 'ANDHRA PRADESH PUBLIC SERVICE COMMISSION::VIJAYAWADA NOTIFICATION NO.29/2025, DATED: 24.09.2025 '
        .'1.1. Applications are invited through online mode for recruitment to the post of Welfare Organiser '
        .'for 10 vacancies in the scale of pay of Rs.25,220 - 80,910 within the maximum age of 45 years as on '
        .'01.07.2025. 1.2. The application submission window will be opened from 09/10/2025 to 29/10/2025 upto 11:00 PM.';

    expect($this->rules->extract($text))
        ->toMatchArray([
            'notification_date' => '2025-09-24',
            'apply_start_date' => '2025-10-09',
            'apply_end_date' => '2025-10-29',
            'total_vacancies' => 10,
            'max_age' => 45,
            'age_reference_date' => '2025-07-01',
        ]);
});

it('reads an IBPS registration portal', function (): void {
    $text = 'Recruitment of Security Guards Important Events Dates Commencement of online registration of '
        .'application 25/08/2026 Closure of registration of application 14/09/2026 Last date for printing your application 29/09/2026';

    expect($this->rules->extract($text))
        ->toMatchArray(['apply_start_date' => '2026-08-25', 'apply_end_date' => '2026-09-14']);
});

it('reads dates day first, never month first', function (): void {
    // 09/10/2025 is the ninth of October. Reading it as September moves the window a month.
    $window = $this->rules->extract('The application window will be opened from 09/10/2025 to 29/10/2025.');

    expect($window['apply_start_date'])->toBe('2025-10-09');
});

it('reads the short month format IBPS uses in its link text', function (): void {
    expect($this->rules->extract('IOB Recruitment of Officers Registration From 08-Sep-26'))
        ->toMatchArray(['apply_start_date' => '2026-09-08']);
});

it('returns no date that is not labelled as one', function (): void {
    // A date with no label saying what it is could be anything: an exam, a hall ticket, a
    // corrigendum. It must come back empty, not as the last date to apply.
    $result = $this->rules->extract('Hall tickets will be released on 12/10/2026. The examination is on 25/10/2026.');

    expect($result)->not->toHaveKey('apply_end_date')
        ->not->toHaveKey('apply_start_date');
});

it('does not mistake a vacancy snapshot date for the age reference date', function (): void {
    $result = $this->rules->extract('Updated Vacancies as on 09.09.2026 for Common Recruitment Process.');

    expect($result)->not->toHaveKey('age_reference_date');
});

it('does not read a relaxation clause as the age limit', function (): void {
    $result = $this->rules->extract('For in-service candidates the upper age limit is raised up to 10 years i.e., from 34 years to 44 years.');

    expect($result)->not->toHaveKey('max_age')->not->toHaveKey('min_age');
});

it('drops a window that closes before it opens', function (): void {
    $result = $this->rules->extract('Online registration window will be open from 29/10/2025 to 09/10/2025.');

    expect($result)->not->toHaveKey('apply_start_date')->not->toHaveKey('apply_end_date');
});

it('reads an RRB CEN important-dates table', function (): void {
    $text = 'IMPORTANT DATES: Date of Indicative Notice in Employment News 21-06-2025 Opening date of Online '
        .'application 28-06-2025 Closing date for Submission of Online Application 28-07-2025 (23:59 hours)';

    expect($this->rules->extract($text))
        ->toMatchArray(['apply_start_date' => '2025-06-28', 'apply_end_date' => '2025-07-28']);
});

it('does not read "on or before the closing date" as a date', function (): void {
    $result = $this->rules->extract('Qualifications must be held on or before the closing date for submitting application against this CEN.');

    expect($result)->not->toHaveKey('apply_end_date');
});

it('reads an APPSC brief notification whose window sentence contains a URL', function (): void {
    $text = 'BRIEF NOTIFICATION NO. 16/2026, Dated: 15/09/2026 DIRECT RECRUITMENT TO THE POST OF FOREST RANGE OFFICERS '
        .'1. Applications are invited online through the Commission’s Website (https://psc.ap.gov.in) from eligible '
        .'candidates from 16/10/2026 to 05/11/2026 up to 11:59 P.M for Recruitment to the post of Forest Range Officers.';

    expect($this->rules->extract($text))
        ->toMatchArray([
            'notification_date' => '2026-09-15',
            'apply_start_date' => '2026-10-16',
            'apply_end_date' => '2026-11-05',
        ]);
});

it('reads RRB labels that end in a full stop', function (): void {
    $text = 'Date of Indicative Notice in Employment News. 25.07.2026 Opening date of Online application. 14.08.2026 '
        .'Closing date for Submission of Online Application. 13.09.2026 (23:59 hours) '
        .'Last Date for Application fee payment for the submitted applications. 15.09.2026';

    // The fee deadline two days later is not the closing date for applications.
    expect($this->rules->extract($text))
        ->toMatchArray(['apply_start_date' => '2026-08-14', 'apply_end_date' => '2026-09-13']);
});

it('reads the TGPSC chronology the way pdftotext lays out its table', function (): void {
    $text = 'Submission of Online Application From 15/07/2026 Last Date & Time of submission of 22/08/2026 at 5:00 PM Online Application';

    expect($this->rules->extract($text))
        ->toMatchArray(['apply_start_date' => '2026-07-15', 'apply_end_date' => '2026-08-22']);
});

it('does not take an exam schedule for the application window', function (): void {
    $result = $this->rules->extract('Candidates who submit applications will be informed. The Computer Based Test will be held from 12/10/2026 to 20/10/2026.');

    expect($result)->not->toHaveKey('apply_start_date')->not->toHaveKey('apply_end_date');
});

it('does not read a rule about how many posts to apply for as a vacancy count', function (): void {
    expect($this->rules->extract('A candidate can apply for 1 post only under this notification.'))
        ->not->toHaveKey('total_vacancies')
        ->and($this->rules->extract('Total Posts 2 Scale of Pay Rs.54,220'))
        ->not->toHaveKey('total_vacancies');
});

it('rejects dates that do not exist', function (): void {
    expect($this->rules->date('31/02/2026'))->toBeNull()
        ->and($this->rules->date('12/13/2026'))->toBeNull()
        ->and($this->rules->date('01/07/1999'))->toBeNull();
});
