<?php

declare(strict_types=1);

use App\Livewire\Learning\FlashcardReview;
use App\Models\Flashcard;
use App\Models\User;
use Livewire\Livewire;

/**
 * The review screen.
 *
 * The screen's one job is to show what the scheduler picked and take a grade. The rule
 * worth testing is the one that is easiest to "improve" away: when the queue is empty the
 * session ends, and there is no button offering more. Spaced repetition works because the
 * algorithm sets the volume.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('requires a login', function (): void {
    $this->get(route('flashcards', ['locale' => 'te']))->assertRedirect();
});

it('renders for a signed-in user', function (): void {
    $this->actingAs($this->user)
        ->get(route('flashcards', ['locale' => 'te']))
        ->assertOk()
        ->assertSeeLivewire(FlashcardReview::class);
});

it('shows a due card and hides the answer until it is asked for', function (): void {
    Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'front' => ['te' => 'ప్రశ్న ఒకటి', 'en' => 'Question one'],
        'back' => ['te' => 'జవాబు ఒకటి', 'en' => 'Answer one'],
        'due_at' => today(),
    ]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->assertSee('ప్రశ్న ఒకటి')
        // Self-testing only works if the answer is genuinely hidden first.
        ->assertDontSee('జవాబు ఒకటి')
        ->call('reveal')
        ->assertSee('జవాబు ఒకటి');
});

it('schedules the card forward on a good grade and moves on', function (): void {
    $card = Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'due_at' => today(),
        'interval_days' => 0,
    ]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->call('reveal')
        ->call('grade', 'good')
        ->assertSet('revealed', false)
        ->assertSet('reviewed', 1);

    expect($card->refresh()->due_at->toDateString())->toBe(today()->addDay()->toDateString());
});

it('ends the session rather than offering more work', function (): void {
    Flashcard::factory()->create(['user_id' => $this->user->id, 'due_at' => today()]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->call('reveal')
        ->call('grade', 'good')
        ->assertSee(__('Nothing else is due. Come back tomorrow — that is how spaced repetition works.'));
});

it('tells a new user where cards come from', function (): void {
    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->assertSee(__('No cards due today'));
});

it('filters to one deck', function (): void {
    Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'deck' => 'Polity',
        'front' => ['te' => 'పాలిటీ కార్డు', 'en' => 'Polity card'],
        'due_at' => today(),
    ]);
    Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'deck' => 'History',
        'front' => ['te' => 'చరిత్ర కార్డు', 'en' => 'History card'],
        'due_at' => today(),
    ]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->call('selectDeck', 'Polity')
        ->assertSee('పాలిటీ కార్డు')
        ->assertDontSee('చరిత్ర కార్డు');
});

it('flags a card that keeps being forgotten', function (): void {
    Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'due_at' => today(),
        'lapse_count' => 4,
    ]);

    // Drilling a card you have forgotten four times is not working. Say so.
    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->assertSee('4');
});

it('walks every card in the deck without skipping any', function (): void {
    // THE REGRESSION THIS GUARDS: if the queue is re-queried each request and walked by
    // index, grading a card drops it from the due list, every later card shifts down one,
    // and the index steps straight over the next one — half the deck is never seen.
    $fronts = [];

    foreach (range(1, 4) as $i) {
        Flashcard::factory()->create([
            'user_id' => $this->user->id,
            'deck' => 'Polity',
            'front' => ['te' => "కార్డు {$i}", 'en' => "Card {$i}"],
            'due_at' => today(),
        ]);
        $fronts[] = "కార్డు {$i}";
    }

    $component = Livewire::actingAs($this->user)->test(FlashcardReview::class);
    $seen = [];

    foreach (range(1, 4) as $ignored) {
        foreach ($fronts as $front) {
            if (str_contains($component->html(), $front)) {
                $seen[] = $front;
            }
        }

        $component->call('reveal')->call('grade', 'good');
    }

    expect($seen)->toHaveCount(4)
        ->and(array_unique($seen))->toHaveCount(4);

    expect(Flashcard::where('user_id', $this->user->id)->where('review_count', 1)->count())->toBe(4);
});

it('brings a forgotten card back in the same session', function (): void {
    // The button says "again today". It has to mean today, not tomorrow.
    Flashcard::factory()->create([
        'user_id' => $this->user->id,
        'front' => ['te' => 'మరచిపోయిన కార్డు', 'en' => 'Forgotten card'],
        'due_at' => today(),
    ]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->call('reveal')
        ->call('grade', 'forgot')
        ->assertSet('index', 1)
        // Back at the end of the queue rather than gone until tomorrow.
        ->assertSee('మరచిపోయిన కార్డు')
        ->assertDontSee(__('No cards due today'));
});

it('ignores a grade that is not one of the four', function (): void {
    $card = Flashcard::factory()->create(['user_id' => $this->user->id, 'due_at' => today()]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->call('grade', 'perfect')
        ->assertSet('reviewed', 0);

    expect($card->refresh()->review_count)->toBe(0);
});

it('never serves a card belonging to someone else', function (): void {
    // The id comes back from the browser in the queue, so ownership is checked on read.
    $other = Flashcard::factory()->create(['user_id' => User::factory(), 'due_at' => today()]);

    Livewire::actingAs($this->user)
        ->test(FlashcardReview::class)
        ->set('queue', [$other->id])
        ->assertSee(__('No cards due today'));

    expect($other->refresh()->review_count)->toBe(0);
});
