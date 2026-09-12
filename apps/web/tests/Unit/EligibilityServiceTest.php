<?php

declare(strict_types=1);

use App\Models\ExamNotification;
use App\Models\Profile;
use App\Services\EligibilityService;

/**
 * The eligibility engine.
 *
 * Target coverage is 95%, the highest in the project, because a wrong answer here misleads
 * someone about whether to spend a year of their life on an exam. Every branch that can
 * change a verdict has a test, and the boundary cases are tested on both sides of the line.
 */
beforeEach(function (): void {
    $this->service = new EligibilityService;
});

/**
 * Build a notification without touching the database. These are pure-function tests: the
 * engine reads fields and returns a verdict, and involving the database would only make
 * them slower and less precise about what is being asserted.
 */
function notification(array $attributes = []): ExamNotification
{
    return new ExamNotification(array_merge([
        'min_qualification' => 'degree',
        'min_age' => 18,
        'max_age' => 30,
        'age_relaxation' => ['obc' => 3, 'sc' => 5, 'st' => 5, 'pwd' => 10, 'ex_serviceman' => 3],
        'age_reference_date' => '2026-07-01',
        'gender_restriction' => 'any',
    ], $attributes));
}

// ---------------------------------------------------------------- the unknown state

it('returns unknown when there is no profile', function (): void {
    $result = $this->service->check(notification(), null);

    expect($result['status'])->toBe('unknown')
        ->and($result['matched'])->toBeEmpty()
        ->and($result['failed'])->toBeEmpty();
});

/**
 * A logged-out user, or one who has not filled in a profile, must never see a verdict.
 * "Eligible" is the answer people act on, and guessing it from an empty profile would be
 * the single most damaging default in the product.
 */
it('returns unknown when the notification states no criteria at all', function (): void {
    $notification = notification([
        'min_qualification' => null,
        'min_age' => null,
        'max_age' => null,
        'allowed_states' => null,
        'gender_restriction' => 'any',
    ]);

    expect($this->service->check($notification, profileAged(25))['status'])->toBe('unknown');
});

// ---------------------------------------------------------------- qualification

it('accepts a qualification above the minimum', function (): void {
    $result = $this->service->check(
        notification(['min_qualification' => '12th']),
        profileAged(25, attributes: ['highest_qualification' => 'degree']),
    );

    expect($result['status'])->toBe('eligible');
});

it('rejects a qualification below the minimum', function (): void {
    $result = $this->service->check(
        notification(['min_qualification' => 'degree']),
        profileAged(25, attributes: ['highest_qualification' => '10th']),
    );

    expect(collect($result['failed'])->pluck('key'))->toContain('qualification');
});

/**
 * B.Tech and a general degree sit at the same rank deliberately. A notification asking for
 * "any degree" is satisfied by either; one asking specifically for B.Tech says so in
 * `qualification_notes`, which a human reads. Encoding stream rules numerically here would
 * produce confident wrong answers.
 */
it('treats btech and degree as equivalent for a degree requirement', function (): void {
    $result = $this->service->check(
        notification(['min_qualification' => 'degree']),
        profileAged(25, attributes: ['highest_qualification' => 'btech']),
    );

    expect($result['status'])->toBe('eligible');
});

/**
 * A missing qualification is NOT a failure. Counting it as one would tell a user they are
 * ineligible because of something they simply have not told us yet.
 */
it('skips the qualification check when the profile does not state one', function (): void {
    $result = $this->service->check(
        notification(['min_qualification' => 'degree']),
        profileAged(25, attributes: ['highest_qualification' => null]),
    );

    expect(collect($result['failed'])->pluck('key'))->not->toContain('qualification');
});

// ---------------------------------------------------------------- age and relaxation

it('applies category age relaxation', function (): void {
    // 33 on the reference date; general limit 30, SC relaxation +5 gives 35.
    $result = $this->service->check(
        notification(['max_age' => 30]),
        profileAged(33, attributes: ['category' => 'sc']),
    );

    expect($result['status'])->toBe('eligible')
        ->and($result['effective_max_age'])->toBe(35);
});

it('stacks pwd relaxation on top of category relaxation', function (): void {
    // 30 + 5 (SC) + 10 (PwD) = 45.
    $result = $this->service->check(
        notification(['max_age' => 30]),
        profileAged(44, attributes: ['category' => 'sc', 'is_pwd' => true]),
    );

    expect($result['effective_max_age'])->toBe(45)
        ->and($result['status'])->toBe('eligible');
});

it('rejects an age beyond even the relaxed limit', function (): void {
    $result = $this->service->check(
        notification(['max_age' => 30]),
        profileAged(40, attributes: ['category' => 'sc']),
    );

    expect(collect($result['failed'])->pluck('key'))->toContain('age');
});

it('rejects an age below the minimum', function (): void {
    $result = $this->service->check(
        notification(['min_age' => 21]),
        profileAged(19),
    );

    expect(collect($result['failed'])->pluck('key'))->toContain('age');
});

/**
 * THE BOUNDARY. Completed years only: someone who turns 31 tomorrow is 30 today, and a
 * notification with a maximum age of 30 accepts them. Both sides are asserted because an
 * off-by-one here silently excludes or admits real candidates.
 */
it('counts completed years, so the last eligible day still qualifies', function (): void {
    $exactly30 = $this->service->check(notification(['max_age' => 30]), profileAged(30));
    $justOver = $this->service->check(notification(['max_age' => 30]), profileAged(31));

    expect($exactly30['status'])->toBe('eligible')
        ->and(collect($justOver['failed'])->pluck('key'))->toContain('age');
});

/**
 * THE MOST IMPORTANT TEST IN THIS FILE.
 *
 * Indian notifications compute age "as on" a stated cut-off, commonly 1 July, NOT as on
 * the application deadline. A candidate can be eligible on the reference date and over the
 * limit by the deadline. Using the wrong date moves them across the line.
 */
it('uses the reference date, not the application deadline', function (): void {
    $notification = notification([
        'max_age' => 30,
        'age_reference_date' => '2026-07-01',
        'apply_end_date' => '2026-12-31',
    ]);

    // Born 1 October 1995: 30 on 1 July 2026, but 31 by the December deadline.
    $profile = new Profile([
        'date_of_birth' => '1995-10-01',
        'highest_qualification' => 'degree',
        'category' => 'general',
        'state' => 'TS',
    ]);

    expect($this->service->check($notification, $profile)['status'])->toBe('eligible');
});

it('falls back to the deadline when no reference date is recorded', function (): void {
    $notification = notification(['age_reference_date' => null, 'apply_end_date' => '2026-07-01']);

    expect($this->service->check($notification, profileAged(30))['status'])->toBe('eligible');
});

// ---------------------------------------------------------------- domicile

it('rejects a candidate from outside the allowed states', function (): void {
    $result = $this->service->check(
        notification(['allowed_states' => ['TS']]),
        profileAged(25, attributes: ['state' => 'AP']),
    );

    expect(collect($result['failed'])->pluck('key'))->toContain('state');
});

/**
 * Empty means all-India, not "no states allowed". Reading it the other way would hide
 * every central government job from every user.
 */
it('treats an empty state list as all-India', function (): void {
    foreach ([null, []] as $states) {
        $result = $this->service->check(
            notification(['allowed_states' => $states]),
            profileAged(25, attributes: ['state' => 'AP']),
        );

        expect($result['status'])->toBe('eligible');
    }
});

// ---------------------------------------------------------------- gender

it('rejects a candidate excluded by a gender restriction', function (): void {
    $result = $this->service->check(
        notification(['gender_restriction' => 'female']),
        profileAged(25, attributes: ['gender' => 'male']),
    );

    expect(collect($result['failed'])->pluck('key'))->toContain('gender');
});

it('skips the gender check when the profile does not state one', function (): void {
    $result = $this->service->check(
        notification(['gender_restriction' => 'female']),
        profileAged(25, attributes: ['gender' => null]),
    );

    expect(collect($result['failed'])->pluck('key'))->not->toContain('gender');
});

// ---------------------------------------------------------------- partial

/**
 * `partial` is a real answer, not a rounding of "no".
 *
 * A user who fails on one criterion still wants to see the notification — they may hold a
 * relaxation we do not know about, or be reading it for a sibling. Never hide content;
 * label it.
 */
it('returns partial when at least half the criteria match', function (): void {
    $result = $this->service->check(
        notification([
            'min_qualification' => 'degree',
            'max_age' => 46,
            'allowed_states' => ['TS'],
        ]),
        profileAged(35, attributes: ['highest_qualification' => '10th', 'state' => 'TS']),
    );

    // age ok, state ok, qualification fails -> 2 of 3.
    expect($result['status'])->toBe('partial')
        ->and($result['score'])->toBeGreaterThanOrEqual(0.5);
});

it('returns not_eligible when fewer than half the criteria match', function (): void {
    $result = $this->service->check(
        notification([
            'min_qualification' => 'degree',
            'min_age' => 18,
            'max_age' => 22,
            'allowed_states' => ['TS'],
        ]),
        profileAged(35, attributes: ['highest_qualification' => '10th', 'state' => 'TS']),
    );

    // only state matches -> 1 of 3.
    expect($result['status'])->toBe('not_eligible')
        ->and($result['score'])->toBeLessThan(0.5);
});

// ---------------------------------------------------------------- the caveat

/**
 * An automated check is guidance, not a legal determination, and every rendered badge must
 * carry that. Asserting it here stops the caveat being dropped in a future refactor.
 */
it('always returns a caveat', function (): void {
    expect($this->service->check(notification(), profileAged(25))['caveat'])->not->toBeEmpty()
        ->and($this->service->check(notification(), null)['caveat'])->not->toBeEmpty();
});
