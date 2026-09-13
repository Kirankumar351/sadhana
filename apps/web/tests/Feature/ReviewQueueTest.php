<?php

declare(strict_types=1);

use App\Filament\Pages\ReviewQueue;
use App\Models\ExamNotification;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The review queue.
 *
 * Nothing reaches users until a person has read the source and said the fields match. These
 * tests are mostly about what the screen REFUSES to publish — a missing age reference date
 * does not look like an error, it quietly tells the wrong people they are ineligible.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->staff()->create();

    $this->pending = ExamNotification::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'tgpsc-junior-assistant-2026',
        'title' => ['en' => 'TGPSC Junior Assistant & Typist 2026'],
        'organisation' => 'Telangana Public Service Commission',
        'total_vacancies' => 1240,
        'min_qualification' => 'degree',
        'min_age' => 18,
        'max_age' => 44,
        'age_reference_date' => '2026-07-01',
        'apply_end_date' => '2026-09-29',
        'official_pdf_url' => 'https://tgpsc.gov.in/n.pdf',
        'status' => 'pending_review',
    ]);
});

function pendingNotification(array $attributes = []): ExamNotification
{
    return ExamNotification::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'item-'.Str::random(8),
        'title' => ['en' => 'Another notification'],
        'organisation' => 'APPSC',
        'status' => 'pending_review',
    ], $attributes));
}

it('opens on the oldest waiting item', function (): void {
    // Oldest first, always: a queue worked newest-first breaks the SLA on exactly the items
    // already closest to breaching it.
    $older = pendingNotification(['created_at' => now()->subHours(5)]);

    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->assertSet('notificationId', $older->id);
});

it('refuses to publish until every line is confirmed', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->call('publish');

    expect($this->pending->refresh()->status)->toBe('pending_review');
});

it('publishes once all four are confirmed', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->set('confirmed', ['dates' => true, 'eligibility' => true, 'fees' => true, 'link' => true])
        ->call('publish');

    $this->pending->refresh();

    expect($this->pending->status)->toBe('published')
        ->and($this->pending->published_at)->not->toBeNull()
        // Who read the source is the first question asked when a date turns out to be wrong.
        ->and($this->pending->verified_by)->toBe($this->admin->id)
        ->and($this->pending->verified_at)->not->toBeNull();
});

it('will not publish an age limit with no reference date', function (): void {
    // The failure that is invisible: with no "as on" date every age check falls back to the
    // apply deadline, and thousands are told they qualify when they do not.
    $this->pending->update(['age_reference_date' => null]);

    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->set('confirmed', ['dates' => true, 'eligibility' => true, 'fees' => true, 'link' => true])
        ->call('publish');

    expect($this->pending->refresh()->status)->toBe('pending_review');
});

it('will not publish without a last date', function (): void {
    $this->pending->update(['apply_end_date' => null]);

    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->set('confirmed', ['dates' => true, 'eligibility' => true, 'fees' => true, 'link' => true])
        ->call('publish');

    expect($this->pending->refresh()->status)->toBe('pending_review');
});

it('lets a reviewer skip without judging', function (): void {
    // Someone who cannot verify right now must be able to move on without either publishing
    // or rejecting. Forcing a decision is how unverified items get published.
    $second = pendingNotification(['created_at' => now()->addMinute()]);

    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->call('skip')
        ->assertSet('notificationId', $second->id);

    expect($this->pending->refresh()->status)->toBe('pending_review');
});

it('clears the checklist when moving to another item', function (): void {
    // A tick carried over from the previous notification is a tick nobody made.
    $second = pendingNotification();

    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->set('confirmed', ['dates' => true, 'eligibility' => true, 'fees' => true, 'link' => true])
        ->call('open', $second->id)
        ->assertSet('confirmed.dates', false)
        ->assertSet('confirmed.link', false);
});

it('rejects without deleting', function (): void {
    Livewire::actingAs($this->admin)
        ->test(ReviewQueue::class)
        ->set('notificationId', $this->pending->id)
        ->call('reject');

    // Kept, so the scraper is not re-tested against it every hour.
    expect($this->pending->refresh()->status)->toBe('cancelled');
});

it('flags what is past SLA in the navigation badge', function (): void {
    pendingNotification(['created_at' => now()->subHours(ReviewQueue::SLA_HOURS + 1)]);

    expect(ReviewQueue::getNavigationBadge())->toBe('2')
        ->and(ReviewQueue::getNavigationBadgeColor())->toBe('danger');
});

it('ignores anything already published', function (): void {
    pendingNotification(['status' => 'published', 'created_at' => now()->subDay()]);

    expect(ReviewQueue::getNavigationBadge())->toBe('1');
});
