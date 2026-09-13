<?php

declare(strict_types=1);

namespace App\Livewire\Ai;

use App\Models\Exam;
use App\Models\GeneratedNote;
use App\Services\AI\AiResult;
use App\Services\AI\Features\NotesGenerator as Generator;
use App\Services\Billing\FeatureGate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The notes generator screen — AI Layer feature 05.
 *
 * Pick an exam, a paper and a topic from the structured syllabus; choose how deep and in
 * which language; get notes built from our own indexed material.
 *
 * THE TOPIC IS PICKED, NOT TYPED. It comes from the exam's structured syllabus, which means
 * every generation is anchored to something that is actually on the paper. A free-text box
 * would invite "explain quantum computing" and produce a confident answer from a corpus
 * that contains nothing about it — the retrieval gate would refuse, and the student would
 * read that refusal as the product being broken rather than as the question being wrong.
 */
class NotesGenerator extends Component
{
    #[Url]
    public ?int $examId = null;

    public ?string $paper = null;

    public string $topic = '';

    public string $depth = 'exam_focused';

    public string $language = 'te';

    public ?AiResult $result = null;

    public bool $generating = false;

    public ?int $savedNoteId = null;

    public function mount(): void
    {
        $this->examId ??= auth()->user()?->primaryExam()?->id;
    }

    public function getExamProperty(): ?Exam
    {
        return $this->examId === null ? null : Exam::find($this->examId);
    }

    /**
     * Papers and topics from the exam's structured syllabus.
     *
     * @return array<string, list<string>>
     */
    public function getSyllabusProperty(): array
    {
        $syllabus = $this->exam?->getTranslation('syllabus', app()->getLocale(), useFallbackLocale: true);

        if (! is_array($syllabus)) {
            return [];
        }

        $papers = [];

        foreach ($syllabus as $paper => $topics) {
            if (is_array($topics)) {
                $papers[(string) $paper] = array_values(array_filter(array_map(
                    static fn ($topic): string => is_string($topic) ? $topic : (string) ($topic['title'] ?? ''),
                    $topics,
                )));
            }
        }

        return $papers;
    }

    /**
     * @return list<string>
     */
    public function getTopicsProperty(): array
    {
        return $this->syllabus[$this->paper] ?? [];
    }

    public function updatedPaper(): void
    {
        $this->topic = '';
    }

    public function getThinCoverageProperty(): bool
    {
        return $this->topic !== ''
            && app(Generator::class)->coverageIsThin($this->topic, $this->exam, app()->getLocale());
    }

    /**
     * Notes this month against the cap, so a student can see where they stand before
     * spending one rather than after.
     */
    public function getUsageProperty(): array
    {
        $user = auth()->user();
        // Which cap applies is an entitlement question, not a plan question.
        $tier = app(FeatureGate::class)->allows($user, 'ai_higher_caps') ? 'premium' : 'free';

        return [
            'used' => $user === null ? 0 : GeneratedNote::usedThisMonth($user->id),
            'limit' => (int) config("ai.caps.{$tier}.notes_per_month", 30),
        ];
    }

    /**
     * @return Collection<int, GeneratedNote>
     */
    public function getSavedProperty(): Collection
    {
        return GeneratedNote::query()
            ->where('user_id', auth()->id())
            ->where('is_saved', true)
            ->latest()
            ->limit(8)
            ->get();
    }

    public function generate(Generator $generator): void
    {
        $this->validate([
            'topic' => ['required', 'string', 'min:3', 'max:240'],
            'depth' => ['required', 'in:exam_focused,detailed,revision'],
            'language' => ['required', 'in:te,en,both'],
        ]);

        $this->savedNoteId = null;
        $this->generating = true;

        $this->result = $generator->generate(
            user: auth()->user(),
            topic: $this->topic,
            depth: $this->depth,
            language: $this->language,
            exam: $this->exam,
            paper: $this->paper,
        );

        $this->generating = false;
    }

    public function save(Generator $generator): void
    {
        if ($this->result === null || ! $this->result->isAnswer() || $this->savedNoteId !== null) {
            return;
        }

        $this->savedNoteId = $generator->save(
            user: auth()->user(),
            result: $this->result,
            topic: $this->topic,
            depth: $this->depth,
            language: $this->language,
            exam: $this->exam,
            paper: $this->paper,
        )->id;
    }

    public function render()
    {
        return view('livewire.ai.notes-generator');
    }
}
