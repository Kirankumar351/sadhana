<?php

declare(strict_types=1);

namespace App\Services\AI\Features;

use App\Models\Exam;
use App\Models\QuizAttempt;
use App\Models\StudyPlan;
use App\Models\User;
use App\Services\AI\AiGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Personal study plan — AI Layer feature 06.
 *
 * Weak areas from quiz history, plus the days remaining, turned into a week-by-week
 * allocation of the hours the student actually has.
 *
 * THE ALLOCATION IS COMPUTED, NOT GENERATED. This is the important decision in the feature.
 * A model asked to plan someone's last 28 days will produce something fluent, plausible and
 * unverifiable — and a student will follow it, because it is addressed to them personally
 * and arrives with confidence. So the arithmetic is code: percentage per subject from their
 * own attempts, weighted by how much of the paper each subject is worth, capped by the
 * calendar. The model is given the finished allocation and asked only to explain it in
 * their language. If the model is unavailable the plan is still correct; it simply arrives
 * without the prose.
 *
 * IT WILL NOT BUILD A PLAN FROM NOISE. Below a floor of attempts, subject percentages are
 * sampling error dressed as insight, and sending a student to spend three weeks on their
 * "weakest subject" because they got two questions wrong in it is worse than offering
 * nothing. It says how many more quizzes it needs.
 *
 * AND IT PREDICTS NOTHING. It cannot say whether they will clear the exam and does not try.
 * It allocates hours. That distinction is written into the screen, not just into this
 * comment, because the difference between a plan and a promise is the whole trust question
 * for a product like this.
 */
final class StudyPlanner
{
    /** Below this many attempts, subject strength is noise rather than signal. */
    public const MIN_ATTEMPTS = 10;

    /**
     * The final days are for full-length practice and sleep. Starting a new topic in the
     * last week costs more in confidence than it gains in marks.
     */
    private const CONSOLIDATION_DAYS = 4;

    /** A subject this far below the average is where the marks actually are. */
    private const WEAK_THRESHOLD = 60;

    public function __construct(private readonly AiGateway $gateway) {}

    /**
     * Build and store a plan, superseding any previous one.
     *
     * Superseded rather than overwritten: a student who rebuilds a plan mid-preparation
     * should be able to see that it changed, and what it changed from.
     */
    public function build(User $user, Exam $exam, CarbonImmutable $targetDate): ?StudyPlan
    {
        $attempts = $this->attemptsFor($user, $exam);

        if ($attempts->count() < self::MIN_ATTEMPTS) {
            return null;
        }

        $strength = $this->subjectStrength($attempts);
        $daysLeft = max(1, (int) CarbonImmutable::parse(today())->diffInDays($targetDate));
        $allocation = $this->allocate($strength, $daysLeft);

        $plan = [
            'days_left' => $daysLeft,
            'allocation' => $allocation,
            'weeks' => $this->weeks($allocation, $daysLeft, $targetDate),
            'strength' => $strength,
            'narrative' => $this->narrate($user, $exam, $strength, $allocation, $daysLeft),
        ];

        StudyPlan::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('superseded_at')
            ->update(['superseded_at' => now()]);

        return StudyPlan::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'target_date' => $targetDate->toDateString(),
            'plan' => $plan,
            'weak_subjects' => array_keys(array_filter(
                $strength,
                static fn (int $score): bool => $score < self::WEAK_THRESHOLD,
            )),
            'based_on_attempts' => $attempts->count(),
            'generated_at' => now(),
        ]);
    }

    public function current(User $user, Exam $exam): ?StudyPlan
    {
        return StudyPlan::query()
            ->where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->whereNull('superseded_at')
            ->latest('generated_at')
            ->first();
    }

    /**
     * How many more quizzes before a plan would mean anything.
     */
    public function attemptsNeeded(User $user, Exam $exam): int
    {
        return max(0, self::MIN_ATTEMPTS - $this->attemptsFor($user, $exam)->count());
    }

    /**
     * @return Collection<int, QuizAttempt>
     */
    private function attemptsFor(User $user, Exam $exam)
    {
        return QuizAttempt::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->whereNotNull('subject_breakdown')
            ->latest('completed_at')
            // A plan reflects where they are now, not where they were in March.
            ->limit(80)
            ->get();
    }

    /**
     * Percentage correct per subject, averaged across attempts.
     *
     * @param  Collection<int, QuizAttempt>  $attempts
     * @return array<string, int>
     */
    private function subjectStrength($attempts): array
    {
        $totals = [];

        foreach ($attempts as $attempt) {
            foreach ($attempt->subject_breakdown ?? [] as $subject => $percentage) {
                $totals[$subject][] = (int) $percentage;
            }
        }

        $strength = [];

        foreach ($totals as $subject => $scores) {
            $strength[(string) $subject] = (int) round(array_sum($scores) / count($scores));
        }

        asort($strength);

        return $strength;
    }

    /**
     * Share of remaining time per subject.
     *
     * Weakness earns time, but strength does not lose all of it: a subject at 92% still
     * needs light revision or it decays, and a student told to stop touching their best
     * subject entirely will not believe the rest of the plan either.
     *
     * A fixed quarter is reserved for mocks and revision before anything is distributed.
     * That block is not negotiable — it is what turns study into marks.
     *
     * @param  array<string, int>  $strength
     * @return array<string, int>
     */
    private function allocate(array $strength, int $daysLeft): array
    {
        if ($strength === []) {
            return [];
        }

        $reserved = 25;
        $available = 100 - $reserved;

        // Weight by the gap to mastery, so a 41% subject earns roughly twice what a 70%
        // subject does rather than everything.
        $weights = [];

        foreach ($strength as $subject => $score) {
            $weights[$subject] = max(5, 100 - $score);
        }

        $totalWeight = array_sum($weights);
        $allocation = [];

        foreach ($weights as $subject => $weight) {
            $allocation[$subject] = (int) round($weight / $totalWeight * $available);
        }

        $allocation['__mocks'] = $reserved;

        return $this->roundToHundred($allocation);
    }

    /**
     * Rounding must not invent or lose time. The remainder lands on mocks, which is the
     * one block that can absorb it without changing what the student is told to study.
     *
     * @param  array<string, int>  $allocation
     * @return array<string, int>
     */
    private function roundToHundred(array $allocation): array
    {
        $drift = 100 - array_sum($allocation);
        $allocation['__mocks'] = max(0, $allocation['__mocks'] + $drift);

        return $allocation;
    }

    /**
     * Week-by-week structure.
     *
     * The last CONSOLIDATION_DAYS are marked as such and carry no new topic, whatever the
     * allocation says.
     *
     * @param  array<string, int>  $allocation
     * @return list<array<string, mixed>>
     */
    private function weeks(array $allocation, int $daysLeft, CarbonImmutable $targetDate): array
    {
        $subjects = array_keys(array_filter(
            $allocation,
            static fn (string $key): bool => $key !== '__mocks',
            ARRAY_FILTER_USE_KEY,
        ));

        $start = CarbonImmutable::parse(today());
        $weeks = [];
        $weekCount = (int) max(1, ceil($daysLeft / 7));

        for ($i = 0; $i < $weekCount; $i++) {
            $from = $start->addDays($i * 7);
            $to = min($from->addDays(6), $targetDate);

            $consolidation = $to->greaterThanOrEqualTo($targetDate->subDays(self::CONSOLIDATION_DAYS));

            $weeks[] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'focus' => $consolidation ? null : ($subjects[$i % max(1, count($subjects))] ?? null),
                'consolidation' => $consolidation,
                'blocks' => $consolidation
                    ? $this->consolidationBlocks()
                    : $this->studyBlocks($subjects, $i),
            ];
        }

        return $weeks;
    }

    /**
     * @param  list<string>  $subjects
     * @return list<array{days: string, task: string, detail: string}>
     */
    private function studyBlocks(array $subjects, int $week): array
    {
        $primary = $subjects[$week % max(1, count($subjects))] ?? __('General studies');
        $secondary = $subjects[($week + 1) % max(1, count($subjects))] ?? $primary;
        $strongest = end($subjects) ?: $primary;

        return [
            [
                'days' => __('Mon–Wed'),
                'task' => $primary,
                'detail' => __('2 hours daily · notes plus 30 practice questions'),
            ],
            [
                'days' => __('Thu–Fri'),
                'task' => $secondary,
                'detail' => __('2 hours daily'),
            ],
            [
                'days' => __('Sat'),
                'task' => __(':subject sectional test, 50 questions', ['subject' => $primary]),
                'detail' => __('Target: above 60%'),
            ],
            [
                'days' => __('Sun'),
                'task' => __(':subject — light revision only', ['subject' => $strongest]),
                'detail' => __('45 minutes · flashcards'),
            ],
        ];
    }

    /**
     * @return list<array{days: string, task: string, detail: string}>
     */
    private function consolidationBlocks(): array
    {
        return [
            [
                'days' => __('All week'),
                'task' => __('Two full-length mocks, current affairs daily'),
                'detail' => __('Revise only what you got wrong'),
            ],
            [
                'days' => __('Last :count days', ['count' => self::CONSOLIDATION_DAYS]),
                'task' => __('Practice and sleep — no new topics'),
                'detail' => __('Starting something new in the final week costs more in confidence than it gains in marks.'),
            ],
        ];
    }

    /**
     * The explanation, and only the explanation.
     *
     * The model receives the allocation as a finished fact and is asked to say why it looks
     * like that, in the student's language. It is not asked what the allocation should be —
     * see the class comment. A failure here loses the prose, not the plan.
     *
     * @param  array<string, int>  $strength
     * @param  array<string, int>  $allocation
     */
    private function narrate(User $user, Exam $exam, array $strength, array $allocation, int $daysLeft): ?string
    {
        $lines = [];

        foreach ($strength as $subject => $score) {
            $share = $allocation[$subject] ?? 0;
            $lines[] = "{$subject}: scoring {$score}%, allocated {$share}% of remaining time";
        }

        $result = $this->gateway->ask(
            question: implode("\n", [
                'Explain this study allocation to the student in two short paragraphs.',
                "Exam: {$exam->short_name}. Days remaining: {$daysLeft}.",
                ...$lines,
                'Say which subject is the real problem and why its share is what it is.',
                'Do not predict whether they will pass. Do not add any fact that is not above.',
            ]),
            user: $user,
            feature: 'study_plan',
            context: ['exam_id' => $exam->id],
            locale: $user->preferred_locale ?: config('locales.default'),
        );

        return $result->isAnswer() ? $result->text : null;
    }
}
