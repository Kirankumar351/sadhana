<?php

declare(strict_types=1);

use App\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests run on SQLite in memory, which is why the migrations are driver-aware: the
 * FULLTEXT index, the 191-char prefix index and the ad_events partitioning are MySQL-only
 * and are skipped here. Everything else is identical to production.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Build a profile for eligibility assertions.
 *
 * `age` is expressed as an age on a reference date rather than a date of birth, because
 * every eligibility case is really a statement about age on the notification's "as on"
 * date — and writing the date of birth by hand is where off-by-one errors creep into the
 * tests themselves.
 */
function profileAged(int $age, string $onDate = '2026-07-01', array $attributes = []): Profile
{
    return new Profile(array_merge([
        'date_of_birth' => CarbonImmutable::parse($onDate)->subYears($age),
        'highest_qualification' => 'degree',
        'category' => 'general',
        'state' => 'TS',
    ], $attributes));
}
