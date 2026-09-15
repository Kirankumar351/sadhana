<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;

/**
 * The application runs on Indian time.
 *
 * config/app.php shipped with the timezone hardcoded to UTC, silently ignoring
 * APP_TIMEZONE=Asia/Kolkata in .env. Nothing failed. Deadlines were judged at midnight UTC —
 * half past five the next morning in India — the 06:45 quiz publish would have run at 12:15
 * in the afternoon, and every streak crossed midnight five and a half hours late.
 */
it('uses Indian time unless deliberately configured otherwise', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Kolkata');
});

it('treats a deadline as closed only once the day has ended in India', function (): void {
    // 23:30 IST on the closing date is still the closing date. In UTC it is 18:00 the same
    // day, which agrees; but at 00:30 IST the next day, UTC still says the old date.
    $this->travelTo(CarbonImmutable::parse('2026-09-23 00:30', 'Asia/Kolkata'));

    expect(today()->toDateString())->toBe('2026-09-23')
        ->and(CarbonImmutable::parse('2026-09-22')->endOfDay()->isPast())->toBeTrue();
});
