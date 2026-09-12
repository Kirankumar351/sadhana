<?php

declare(strict_types=1);

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\ExamNotification;
use App\Models\Profile;
use App\Models\User;

beforeEach(function (): void {
    $category = ExamCategory::create(['slug' => 'state-psc', 'name' => ['en' => 'State PSC', 'te' => 'రాష్ట్ర PSC']]);

    $this->exam = Exam::create([
        'category_id' => $category->id,
        'slug' => 'tgpsc-group-2',
        'name' => ['en' => 'TGPSC Group 2', 'te' => 'TGPSC గ్రూప్ 2'],
        'short_name' => 'Group 2',
        'conducting_body' => 'TGPSC',
        'state' => 'TS',
    ]);

    $this->notification = ExamNotification::create([
        'exam_id' => $this->exam->id,
        'slug' => 'tgpsc-group-2-2026',
        'title' => ['en' => 'TGPSC Group 2 2026', 'te' => 'TGPSC గ్రూప్ 2 2026'],
        'organisation' => 'TGPSC',
        'min_qualification' => 'degree',
        'min_age' => 18,
        'max_age' => 30,
        'age_reference_date' => '2026-07-01',
        'apply_end_date' => today()->addDays(20),
        'status' => 'published',
        'published_at' => now(),
    ]);
});

it('serves every public page in both languages', function (string $path): void {
    $this->get($path)->assertSuccessful();
})->with([
    '/te', '/en',
    '/te/notifications', '/en/notifications',
    '/te/exams', '/en/exams',
    '/te/exams/tgpsc-group-2', '/en/exams/tgpsc-group-2',
    '/te/notifications/tgpsc-group-2-2026',
    '/te/quiz', '/te/leaderboard', '/te/sign-in',
]);

it('redirects the bare root to a locale', function (): void {
    $this->get('/')->assertRedirect();
});

/**
 * Slugs are never translated. One slug across every locale, or backlinks fragment and the
 * ranking the whole acquisition model depends on is split in half.
 */
it('serves the same slug in every locale', function (): void {
    $this->get('/te/exams/tgpsc-group-2')->assertSuccessful();
    $this->get('/en/exams/tgpsc-group-2')->assertSuccessful();
});

/**
 * hreflang must list every active locale and point at identical slugs. Google's rule is
 * reciprocity: if the cluster does not point back at itself it is ignored entirely.
 */
it('emits reciprocal hreflang tags', function (): void {
    $this->get('/te/exams/tgpsc-group-2')
        ->assertSee('hreflang="te-IN"', false)
        ->assertSee('hreflang="en-IN"', false)
        ->assertSee('hreflang="x-default"', false);
});

it('emits a canonical pointing at the current locale', function (): void {
    $this->get('/en/exams/tgpsc-group-2')
        ->assertSee('rel="canonical"', false)
        ->assertSee('/en/exams/tgpsc-group-2', false);
});

/**
 * JobPosting structured data is what puts a notification into Google Jobs — a free traffic
 * source the incumbents in this market largely ignore.
 */
it('emits JobPosting structured data on a notification', function (): void {
    $this->get('/te/notifications/tgpsc-group-2-2026')
        ->assertSee('"@type":"JobPosting"', false)
        ->assertSee('validThrough', false);
});

it('emits Course structured data on an exam hub', function (): void {
    $this->get('/te/exams/tgpsc-group-2')->assertSee('"@type":"Course"', false);
});

// ---------------------------------------------------------------- eligibility in the UI

/**
 * A logged-out visitor sees no badge at all — not a misleading one. Eligibility cannot be
 * computed without a date of birth, and guessing it is the answer people act on.
 */
it('shows no eligibility verdict to a guest', function (): void {
    $this->get('/en/notifications')
        ->assertSee('Add your details')
        ->assertDontSee('You are eligible');
});

it('shows an eligible badge to a qualifying user', function (): void {
    $user = User::factory()->create();
    Profile::create([
        'user_id' => $user->id,
        'date_of_birth' => '2001-01-01',   // 25 on the reference date
        'highest_qualification' => 'degree',
        'category' => 'general',
        'state' => 'TS',
    ]);

    $this->actingAs($user)->get('/en/notifications')->assertSee('You are eligible');
});

it('never hides a notification the user does not qualify for', function (): void {
    $user = User::factory()->create();
    Profile::create([
        'user_id' => $user->id,
        'date_of_birth' => '1980-01-01',   // far over the age limit
        'highest_qualification' => '10th',
        'category' => 'general',
        'state' => 'TS',
    ]);

    // Still listed — labelled, not hidden.
    $this->actingAs($user)->get('/en/notifications')->assertSee('TGPSC Group 2 2026');
});

// ---------------------------------------------------------------- visibility rules

it('hides notifications that are not published', function (): void {
    $this->notification->update(['status' => 'draft']);

    $this->get('/en/notifications')->assertDontSee('TGPSC Group 2 2026');
    $this->get('/te/notifications/tgpsc-group-2-2026')->assertNotFound();
});

it('hides notifications whose deadline has passed', function (): void {
    $this->notification->update(['apply_end_date' => today()->subDay()]);

    $this->get('/en/notifications')->assertDontSee('TGPSC Group 2 2026');
});

/**
 * An unknown deadline is not the same as an expired one. Hiding a notification because
 * nobody recorded its last date would lose a real job from the feed.
 */
it('keeps notifications with no recorded deadline visible', function (): void {
    $this->notification->update(['apply_end_date' => null]);

    $this->get('/en/notifications')->assertSee('TGPSC Group 2 2026');
});

// ---------------------------------------------------------------- auth

it('sends guests to sign-in from a protected page', function (string $path): void {
    $this->get($path)->assertRedirect('/te/sign-in');
})->with(['/te/dashboard', '/te/profile', '/te/saved']);

it('lets a signed-in user reach the dashboard', function (): void {
    $this->actingAs(User::factory()->create())->get('/te/dashboard')->assertSuccessful();
});

it('rejects a phone number that is not a valid Indian mobile', function (): void {
    $this->post('/te/sign-in', ['phone' => '1234567890'])->assertSessionHasErrors('phone');
});
