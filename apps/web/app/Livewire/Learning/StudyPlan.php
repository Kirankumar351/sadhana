<?php

declare(strict_types=1);

namespace App\Livewire\Learning;

use App\Models\Exam;
use App\Models\QuizAttempt;
use App\Models\StudyPlan as Plan;
use App\Services\AI\Features\StudyPlanner;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * The study plan screen — AI Layer feature 06.
 *
 * Shows the current plan, or explains honestly why there is not one yet.
 *
 * A PLAN, NOT A PREDICTION. It cannot tell a student whether they will clear the exam and
 * does not try; it allocates the hours they have across the topics where those hours are
 * worth the most. That sentence is on the screen, not only in this comment, because the
 * difference between the two is the entire trust question for a feature addressed this
 * personally.
 */
class StudyPlan extends Component
{
    public ?int $examId = null;

    public bool $building = false;

    public function mount(): void
    {
        $this->examId ??= auth()->user()?->primaryExam()?->id;
    }

    public function getExamProperty(): ?Exam
    {
        return $this->examId === null ? null : Exam::find($this->examId);
    }

    public function getPlanProperty(): ?Plan
    {
        if ($this->exam === null) {
            return null;
        }

        return app(StudyPlanner::class)->current(auth()->user(), $this->exam);
    }

    /**
     * The date the student is actually working towards.
     *
     * Their own target from the exam preference, because that is the date they have in their
     * head. A plan built against a different date is a plan they will quietly ignore.
     */
    public function getTargetDateProperty(): ?CarbonImmutable
    {
        $target = auth()->user()
            ?->examPreferences()
            ->where('exams.id', $this->examId)
            ->first()?->pivot?->target_date;

        return $target === null ? null : CarbonImmutable::parse($target);
    }

    public function getAttemptsNeededProperty(): int
    {
        if ($this->exam === null) {
            return StudyPlanner::MIN_ATTEMPTS;
        }

        return app(StudyPlanner::class)->attemptsNeeded(auth()->user(), $this->exam);
    }

    /**
     * Average daily study, from attempt timings. Shown only once there is enough of it to
     * be a fact rather than an impression.
     */
    public function getDailyMinutesProperty(): ?int
    {
        $seconds = QuizAttempt::query()
            ->where('user_id', auth()->id())
            ->where('completed_at', '>=', now()->subDays(14))
            ->sum('time_taken_sec');

        return $seconds > 0 ? (int) round($seconds / 14 / 60) : null;
    }

    public function rebuild(StudyPlanner $planner): void
    {
        if ($this->exam === null || $this->targetDate === null) {
            return;
        }

        $this->building = true;

        $planner->build(auth()->user(), $this->exam, $this->targetDate);

        $this->building = false;
    }

    public function render()
    {
        return view('livewire.learning.study-plan');
    }
}
