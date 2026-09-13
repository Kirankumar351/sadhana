<?php

declare(strict_types=1);

namespace App\Services\TestSeries;

use App\Jobs\BuildFlashcardsFromAttempt;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\Test;
use App\Models\TestResult;
use Illuminate\Support\Facades\DB;

/**
 * Scoring a full-length mock, and the analytics that make it worth taking.
 *
 * THE ANALYTICS ARE THE PRODUCT, NOT THE SCORE. A student already knows roughly how they
 * did; what they cannot work out alone is which topic is costing them marks and where they
 * are spending time badly. Vol 1 is explicit that post-test analysis is what Testbook does
 * well and what everyone else does badly.
 *
 * Negative marking is applied exactly as the real paper applies it. A mock that scores
 * more generously than the exam teaches a student to guess, and that habit costs them
 * marks on the day that counts.
 */
final class TestScorer
{
    /**
     * @param  array<int, array{q: int, a: int|null, t: int|null}>  $answers
     */
    public function score(Test $test, QuizAttempt $attempt, array $answers): TestResult
    {
        $sections = $test->sections()->orderBy('sequence')->get();

        $questionIds = $sections->pluck('question_ids')->flatten()->unique()->all();
        $questions = Question::query()->whereIn('id', $questionIds)->get()->keyBy('id');

        $byId = collect($answers)->keyBy('q');

        $score = 0.0;
        $sectionBreakdown = [];
        $topicTotals = [];
        $timePerQuestion = [];

        foreach ($sections as $section) {
            $sectionScore = 0.0;
            $correct = 0;
            $wrong = 0;
            $skipped = 0;

            foreach ($section->question_ids as $questionId) {
                $question = $questions->get($questionId);

                if ($question === null) {
                    continue;
                }

                $row = $byId->get($questionId);
                $chosen = $row['a'] ?? null;
                $seconds = $row['t'] ?? null;

                if ($seconds !== null) {
                    $timePerQuestion[$questionId] = $seconds;
                }

                $topic = $question->subject ?? 'General';
                $topicTotals[$topic]['total'] = ($topicTotals[$topic]['total'] ?? 0) + 1;

                /**
                 * A skipped question is NOT a wrong one.
                 *
                 * Under negative marking, choosing not to answer is a legitimate strategy
                 * and often the correct one. Penalising a skip would score the paper
                 * differently from the real exam and teach exactly the wrong instinct.
                 */
                if ($chosen === null) {
                    $skipped++;

                    continue;
                }

                if ($question->isCorrect((int) $chosen)) {
                    $correct++;
                    $sectionScore += (float) $section->marks_per_question;
                    $topicTotals[$topic]['correct'] = ($topicTotals[$topic]['correct'] ?? 0) + 1;
                } else {
                    $wrong++;
                    $sectionScore -= (float) $test->negative_marking;
                }
            }

            $score += $sectionScore;

            $sectionBreakdown[] = [
                'name' => (string) $section->name,
                'score' => round($sectionScore, 2),
                'correct' => $correct,
                'wrong' => $wrong,
                'skipped' => $skipped,
            ];
        }

        $topicStrength = [];

        foreach ($topicTotals as $topic => $counts) {
            $topicStrength[$topic] = (int) round(
                ($counts['correct'] ?? 0) / max($counts['total'], 1) * 100
            );
        }

        asort($topicStrength);

        $result = DB::transaction(function () use (
            $attempt, $test, $score, $sectionBreakdown, $topicStrength, $timePerQuestion
        ): TestResult {
            $attempt->update([
                'score' => round($score, 2),
                'total_marks' => $test->total_marks,
                'subject_breakdown' => $topicStrength,
                'completed_at' => now(),
            ]);

            return TestResult::create([
                'quiz_attempt_id' => $attempt->id,
                'test_id' => $test->id,
                'user_id' => $attempt->user_id,
                'score' => round($score, 2),
                'section_breakdown' => $sectionBreakdown,
                'topic_strength' => $topicStrength,
                'time_per_question' => $timePerQuestion,
                // The three weakest topics. More than three is a list nobody acts on.
                'weak_areas' => array_slice(array_keys($topicStrength), 0, 3),
            ]);
        });

        // A mock test is the richest source of mistakes there is — three hours of them.
        // Dispatched after the transaction so a worker can never pick the attempt up
        // before it is committed.
        BuildFlashcardsFromAttempt::dispatch($attempt->id);

        return $result;
    }

    /**
     * Percentile and rank, recomputed on a schedule rather than read live.
     *
     * Both are relative to everyone who has taken the same test, so they change as more
     * attempts arrive. Computing them per page view would mean a full scan of the attempt
     * table on a page students refresh repeatedly.
     */
    public function rank(Test $test): void
    {
        $results = TestResult::query()
            ->where('test_id', $test->id)
            ->orderByDesc('score')
            ->get();

        $total = $results->count();

        if ($total === 0) {
            return;
        }

        // Top 10% average, so a student can see what a strong attempt actually looked like
        // rather than only their own number.
        $topSlice = max(1, (int) ceil($total * 0.1));
        $topAverage = round($results->take($topSlice)->avg('score'), 2);

        foreach ($results as $index => $result) {
            $result->updateQuietly([
                'rank' => $index + 1,
                'total_attempts' => $total,
                // Percentile is the share scoring BELOW you, which is what a rank
                // predictor means by it — not the share you beat including yourself.
                'percentile' => round(($total - ($index + 1)) / $total * 100, 2),
                'comparison_to_top' => ['top_10_percent_average' => $topAverage],
            ]);
        }
    }
}
