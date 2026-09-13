<?php

declare(strict_types=1);

namespace App\Services\AI\Features;

use App\Models\AnswerEvaluation;
use App\Models\MainsQuestion;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\AiStructured;

/**
 * Descriptive answer evaluation — AI Layer feature 07.
 *
 * For Group 1 mains. A written answer, scored against a published rubric, with what was
 * missing and the one change that would gain the most.
 *
 * THIS IS FEEDBACK, NOT A MARK. No model can predict what an actual examiner will award,
 * and pretending otherwise is the fastest way to lose a serious candidate's trust — they
 * will check it against their real result. What it can do reliably is tell them which
 * required points they did not write, whether their structure follows the expected pattern,
 * whether they answered the directive word, and where they wasted words. That is what
 * improves scores, and every one of those is checkable against the rubric rather than a
 * matter of opinion.
 *
 * THE RUBRIC IS PUBLISHED AND FROZEN. The student can see exactly what is being scored
 * before they write, and the rubric as applied is copied onto the evaluation row — so a
 * later rubric revision never silently rewrites the history a student is measuring their
 * progress against.
 *
 * THE WORD COUNT IS COUNTED IN CODE. It is the one number here that is objectively
 * checkable, so asking a model for it would be inviting an error into the only part of the
 * feedback that cannot be wrong.
 */
final class AnswerEvaluator
{
    public function __construct(private readonly AiGateway $gateway) {}

    public function evaluate(User $user, MainsQuestion $question, string $answer): AiStructured
    {
        $result = $this->gateway->structured(
            instruction: $this->instruction($question),
            content: $answer,
            schema: $this->schema(),
            user: $user,
            feature: 'answer_eval',
        );

        if (! $result->succeeded()) {
            return $result;
        }

        $this->store($user, $question, $answer, $result->data);

        return $result;
    }

    /**
     * Words this student has written, counted here rather than asked for.
     *
     * Telugu is not space-delimited the way English is, but it is written with spaces
     * between words in practice, so a whitespace split is honest for both scripts. It is
     * also what a student counts when they count.
     */
    public function wordCount(string $answer): int
    {
        $words = preg_split('/\s+/u', trim($answer), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }

    /**
     * The habit that is costing the most marks across their history.
     *
     * A student who has left out a conclusion in twelve of eighteen answers has one habit
     * to fix, not twelve content gaps — and nobody spots that from reading one evaluation
     * at a time. This is the thing the feature knows that the student cannot.
     *
     * @return array{gap: string, count: int, total: int}|null
     */
    public function recurringGap(User $user): ?array
    {
        $evaluations = AnswerEvaluation::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(30)
            ->get();

        if ($evaluations->count() < 5) {
            // Below this, "most common gap" is just the last thing that happened.
            return null;
        }

        $counts = [];

        foreach ($evaluations as $evaluation) {
            foreach ($evaluation->points_missed ?? [] as $missed) {
                $label = is_array($missed) ? ($missed['point'] ?? null) : $missed;

                if (is_string($label) && $label !== '') {
                    $counts[$label] = ($counts[$label] ?? 0) + 1;
                }
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);
        $gap = (string) array_key_first($counts);

        return [
            'gap' => $gap,
            'count' => $counts[$gap],
            'total' => $evaluations->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(User $user, MainsQuestion $question, string $answer, array $data): AnswerEvaluation
    {
        return AnswerEvaluation::create([
            'user_id' => $user->id,
            'mains_question_id' => $question->id,
            'answer_text' => $answer,
            // Frozen: a rubric revision must never rewrite an evaluation a student has
            // already read and acted on.
            'rubric' => $question->rubric,
            'band' => isset($data['band']) ? min((float) $data['band'], (float) $question->marks) : null,
            'word_count' => $this->wordCount($answer),
            'points_hit' => $data['points_hit'] ?? [],
            'points_missed' => $data['points_missed'] ?? [],
            'single_biggest_gain' => $data['single_biggest_gain'] ?? null,
        ]);
    }

    private function instruction(MainsQuestion $question): string
    {
        $rubric = json_encode($question->rubric, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $directive = $question->directive ?? 'discuss';

        return implode("\n", array_filter([
            'You are evaluating one answer to an Indian state civil services mains question.',
            '',
            'Question: '.$question->question,
            'Marks: '.$question->marks,
            $question->word_limit ? 'Word limit: '.$question->word_limit : null,
            'Directive word: '.$directive,
            'Rubric: '.$rubric,
            '',
            'Judge ONLY against the rubric above.',
            // The distinction the whole feature rests on.
            'The band is indicative feedback, never a predicted mark. Do not say what an',
            'examiner will award, and do not say whether the candidate will pass.',
            '',
            'Check explicitly whether the directive word was answered. "Examine" and',
            '"critically analyse" require a judgement, not a description — an answer that',
            'only describes has not answered the question that was asked, however accurate',
            'it is.',
            '',
            'Check explicitly whether there is a conclusion. A long-form answer without one',
            'loses marks structurally, whatever its content.',
            '',
            'For every missed point, say why it matters in one short line. "Examiners reward',
            'specific dates in a question that names an agreement" is useful; "you did not',
            'mention the date" is not.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'band' => ['type' => 'number', 'description' => 'Indicative band out of the marks available'],
                'points_hit' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'points_missed' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'point' => ['type' => 'string'],
                            'why' => ['type' => 'string'],
                        ],
                    ],
                ],
                'directive_answered' => ['type' => 'boolean'],
                'has_conclusion' => ['type' => 'boolean'],
                'single_biggest_gain' => [
                    'type' => 'string',
                    'description' => 'The one change that would gain the most marks, with the rough word cost',
                ],
            ],
            'required' => ['band', 'points_hit', 'points_missed', 'single_biggest_gain'],
        ];
    }
}
