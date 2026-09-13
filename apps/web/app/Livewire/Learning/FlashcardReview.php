<?php

declare(strict_types=1);

namespace App\Livewire\Learning;

use App\Models\Flashcard;
use App\Services\Learning\SpacedRepetition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Flashcard review — AI Layer feature 09.
 *
 * The deck the student should see today, one card at a time, graded by how well they
 * recalled it. The scheduler decides what comes back and when; the student only answers.
 *
 * THE MOST VALUABLE DECK IS BUILT FROM THEIR OWN WRONG ANSWERS. A card generated from a
 * syllabus topic is useful; a card generated from a question they actually got wrong last
 * Tuesday is the one that moves their score. CardsFromMistakes writes those after every
 * quiz and every mock.
 *
 * THE QUEUE IS FIXED AT THE START OF THE SESSION, AS A LIST OF IDS. It is tempting to just
 * re-run "what is due now" on every request and walk it with an index — and that quietly
 * skips half the deck, because grading a card removes it from the due list and every
 * remaining card shifts down one position under the index. The queue is therefore decided
 * once and only appended to.
 */
class FlashcardReview extends Component
{
    public ?string $deck = null;

    /**
     * Card ids for this session, in order. Public so it survives the roundtrip: recomputing
     * it per request is exactly the bug described above.
     *
     * @var list<int>
     */
    public array $queue = [];

    public int $index = 0;

    public bool $revealed = false;

    public int $reviewed = 0;

    public function mount(): void
    {
        $this->loadQueue();
    }

    private function loadQueue(): void
    {
        $this->queue = app(SpacedRepetition::class)
            ->dueFor(auth()->id(), $this->deck)
            ->pluck('id')
            ->all();

        $this->index = 0;
    }

    /**
     * The card at the front of the queue.
     *
     * A plain method, deliberately not a computed property: Livewire caches computed
     * properties for the whole request, so a grade that advances the index would still
     * render the card that was just answered.
     */
    private function currentCard(): ?Flashcard
    {
        $id = $this->queue[$this->index] ?? null;

        return $id === null ? null : Flashcard::query()
            ->where('user_id', auth()->id())
            ->find($id);
    }

    public function reveal(): void
    {
        $this->revealed = true;
    }

    /**
     * Grade the current card and move on.
     *
     * The four grades map to how the student actually felt, not to a numeric scale. "I
     * knew it but it took a moment" is a different signal from "I got it instantly", and
     * asking for a 0-5 rating in that moment is a question nobody answers honestly.
     */
    public function grade(string $grade, SpacedRepetition $scheduler): void
    {
        if (! in_array($grade, ['forgot', 'hard', 'good', 'easy'], true)) {
            return;
        }

        $card = $this->currentCard();

        if ($card === null) {
            return;
        }

        $scheduler->review($card, $grade, $this->examDate());

        // "Forgot" is labelled "again today" on the button, so it has to mean that. The card
        // goes to the back of this session's queue rather than waiting for tomorrow — a card
        // you just failed is the one worth a second look while the correction is fresh.
        if ($grade === 'forgot') {
            $this->queue[] = $card->id;
        }

        $this->reviewed++;
        $this->revealed = false;
        $this->index++;
    }

    /**
     * The exam the student is actually sitting, so intervals can be capped by it.
     *
     * Their primary exam when they have marked one, otherwise the nearest target date —
     * scheduling against an exam they are not taking is worse than not capping at all.
     */
    private function examDate(): ?CarbonImmutable
    {
        $target = auth()->user()
            ?->examPreferences()
            ->wherePivotNotNull('target_date')
            ->orderByPivot('is_primary', 'desc')
            ->orderByPivot('target_date')
            ->first()?->pivot?->target_date;

        return $target === null ? null : CarbonImmutable::parse($target);
    }

    /**
     * Decks with their due counts.
     *
     * Due counts rather than totals, because the only number that decides what to do next is
     * how many are waiting.
     */
    public function getDecksProperty(): Collection
    {
        return Flashcard::query()
            ->where('user_id', auth()->id())
            ->selectRaw('deck, count(*) as total, sum(case when due_at is null or due_at <= ? then 1 else 0 end) as due', [today()->toDateString()])
            ->groupBy('deck')
            ->orderByDesc('due')
            ->get();
    }

    public function selectDeck(?string $deck): void
    {
        $this->deck = $deck;
        $this->reset(['revealed', 'reviewed']);
        $this->loadQueue();
    }

    public function render()
    {
        return view('livewire.learning.flashcard-review', [
            'card' => $this->currentCard(),
            'total' => count($this->queue),
        ]);
    }
}
