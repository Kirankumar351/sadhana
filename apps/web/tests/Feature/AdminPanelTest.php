<?php

declare(strict_types=1);

use App\Models\ExamNotification;
use App\Models\User;

/**
 * The admin portal.
 *
 * Access control is tested first and hardest. Everything published from this panel reaches
 * lakhs of people, and every user-data read is meant to be attributable — both of which
 * depend on only the right people getting in.
 */
it('refuses the admin panel to a guest', function (): void {
    $this->get('/admin')->assertRedirect();
});

it('refuses the admin panel to an ordinary signed-in user', function (): void {
    $user = User::factory()->create(['is_staff' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

/**
 * A banned staff account is refused even though the staff flag is still set.
 *
 * Deactivating someone has to take effect immediately. Requiring an admin to remember to
 * clear a second column is how a revoked account keeps working for another week.
 */
it('refuses the admin panel to a banned staff member', function (): void {
    $user = User::factory()->staff()->banned()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('lets a staff member in', function (): void {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

it('renders every admin resource for a staff member', function (string $path): void {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)->get($path)->assertSuccessful();
})->with([
    // Content
    '/admin/exam-notifications',
    '/admin/exam-notifications/create',
    '/admin/exams',
    '/admin/exam-categories',
    '/admin/materials',

    // Quiz
    '/admin/questions',
    '/admin/daily-quizzes',
    '/admin/test-series',
    '/admin/tests',

    // AI — a content editor may draft, but prompts and agents are Owner-only.
    '/admin/news-items',
    '/admin/ai-drafts',
    '/admin/ai-prompts',
    '/admin/agent-definitions',
    '/admin/agent-runs',

    // Community
    '/admin/posts',
    '/admin/answers',
    '/admin/moderation-flags',

    // Localisation
    '/admin/glossaries',
    '/admin/translation-queues',

    // Money
    '/admin/plans',
    '/admin/orders',
    '/admin/subscriptions',
    '/admin/coupons',
    '/admin/advertisers',
    '/admin/ad-campaigns',

    // People and system
    '/admin/users',
    '/admin/scrape-sources',
    '/admin/data-requests',
    '/admin/feature-flags',
    '/admin/audit-logs',

    // The cost page. The free product only works while this number stays small.
    '/admin/ai-cost',
]);

/**
 * Nothing created in the panel starts published.
 *
 * Publishing is a separate action with its own verification checklist, and there is no
 * path through this panel that skips it.
 */
it('creates notifications as drafts, never published', function (): void {
    $notification = ExamNotification::create([
        'slug' => 'test-notification',
        'title' => ['en' => 'Test'],
        'organisation' => 'Test Board',
    ]);

    // refresh(): the 'draft' default is applied by the database on insert, so the
    // in-memory instance does not carry it until the row is read back.
    expect($notification->refresh()->status)->toBe('draft')
        ->and($notification->published_at)->toBeNull();
});

/**
 * An unverified notification is visibly unverified.
 *
 * The list filters on this precisely so a published-but-unchecked row cannot sit unnoticed,
 * which is the state that produces a wrong date in front of a student.
 */
it('can find published notifications that were never verified', function (): void {
    ExamNotification::create([
        'slug' => 'unverified-one',
        'title' => ['en' => 'Unverified'],
        'organisation' => 'Board',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $count = ExamNotification::query()
        ->where('status', 'published')
        ->whereNull('verified_at')
        ->count();

    expect($count)->toBe(1);
});
