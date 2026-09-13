<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Models\Exam;
use App\Models\InterviewSession;
use App\Models\InterviewTurn;
use App\Services\AI\Features\MockInterview as Interviewer;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Mock interview — AI Layer feature 08.
 *
 * Bio-data first, then a conversation. The bio-data form comes before the interview because
 * it IS the interview: the questions a board asks come off that form, and a candidate who
 * fills it in honestly gets the rehearsal they actually need.
 */
class MockInterview extends Component
{
    public ?int $sessionId = null;

    public string $district = '';

    public string $graduation = '';

    public string $hobbies = '';

    public string $optional = '';

    public string $language = 'te';

    public string $answer = '';

    public ?string $error = null;

    public function mount(): void
    {
        $profile = auth()->user()?->profile;

        // Pre-filled from what we already know, because retyping a district is friction in
        // front of the one feature that needs the candidate to be candid.
        $this->district = (string) ($profile?->district ?? '');
    }

    public function getSessionProperty(): ?InterviewSession
    {
        if ($this->sessionId === null) {
            return null;
        }

        return InterviewSession::query()
            ->where('user_id', auth()->id())
            ->with(['turns' => fn ($query) => $query->orderBy('turn_index')])
            ->find($this->sessionId);
    }

    /**
     * @return Collection<int, InterviewTurn>
     */
    public function getTurnsProperty(): Collection
    {
        return $this->session?->turns ?? collect();
    }

    public function getCurrentQuestionProperty(): ?string
    {
        if ($this->session === null) {
            return null;
        }

        return app(Interviewer::class)->currentTurn($this->session)?->question;
    }

    public function getElapsedProperty(): int
    {
        return (int) ($this->session?->created_at->diffInMinutes(now()) ?? 0);
    }

    public function start(Interviewer $interviewer): void
    {
        $this->validate([
            'district' => ['required', 'string', 'max:80'],
            'graduation' => ['required', 'string', 'max:120'],
            'hobbies' => ['nullable', 'string', 'max:200'],
            'optional' => ['nullable', 'string', 'max:120'],
            'language' => ['required', 'in:te,en,mixed'],
        ]);

        if (! $interviewer->canStart(auth()->user())) {
            $this->error = __('You have used this month’s mock interviews.');

            return;
        }

        $exam = auth()->user()?->primaryExam() ?? Exam::query()->where('has_interview', true)->first();

        if ($exam === null) {
            $this->error = __('Choose an exam with an interview stage first.');

            return;
        }

        $this->sessionId = $interviewer->start(
            auth()->user(),
            $exam,
            array_filter([
                'district' => $this->district,
                'graduation' => $this->graduation,
                'hobbies' => $this->hobbies,
                'optional' => $this->optional,
            ]),
            $this->language,
        )->id;
    }

    public function reply(Interviewer $interviewer): void
    {
        $this->validate([
            // A board would not accept three words either.
            'answer' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        if ($this->session === null) {
            return;
        }

        $result = $interviewer->answer($this->session, $this->answer);

        $this->error = $result->succeeded() ? null : match ($result->status) {
            'cap_reached' => __('You have used this month’s mock interviews.'),
            default => __('The board lost its thread for a moment. Please send that again.'),
        };

        if ($result->succeeded()) {
            $this->answer = '';
        }
    }

    public function render()
    {
        return view('livewire.ai.mock-interview');
    }
}
