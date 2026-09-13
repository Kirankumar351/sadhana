<?php

declare(strict_types=1);

namespace App\Services\AI\Features;

use App\Models\Exam;
use App\Models\InterviewSession;
use App\Models\InterviewTurn;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\AiStructured;
use App\Services\AI\CostMeter;
use App\Services\AI\Exceptions\CapExceededException;

/**
 * Mock interview — AI Layer feature 08.
 *
 * Group 1 interview practice. Questions built from the candidate's own bio-data, in Telugu
 * or English, with feedback after each answer and a report at the end.
 *
 * PRACTICE, NOT SIMULATION. A real board reads body language, composure and the candidate's
 * file. This cannot and does not pretend to. What it can do is give repetitions on the
 * questions a particular bio-data invites — home district, graduation subject, hobbies as
 * written on the form, optional subject — which is precisely the part a candidate can
 * actually prepare, and the part they most often have nobody to rehearse with.
 *
 * THE BIO-DATA IS THE WHOLE FEATURE. A board asks a candidate from Karimnagar about
 * Sriram Sagar, and a B.Sc Computer Science graduate why they are not in the private sector.
 * Generic questions are already in every guidebook; the ones that come out of their own form
 * are the ones they have not thought about.
 *
 * FOLLOW-UPS ARE THE POINT, NOT A FEATURE. A board rarely accepts the first answer. The
 * second question — the one that presses on what was just said — is where candidates come
 * apart, and it is the one thing a static question bank cannot give them.
 */
final class MockInterview
{
    /** A real Group 1 board runs roughly this long. */
    public const TARGET_QUESTIONS = 20;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly CostMeter $meter,
    ) {}

    /**
     * Whether this candidate has an interview left this month.
     *
     * Checked before the session is created rather than on the first answer: starting a
     * mock, filling in the bio-data and then being told there is no allowance left is a
     * worse experience than being told at the door.
     */
    public function canStart(User $user): bool
    {
        try {
            $this->meter->assertWithinCaps($user, 'mock_interview');

            return true;
        } catch (CapExceededException) {
            return false;
        }
    }

    /**
     * @param  array<string, string>  $bioData
     */
    public function start(User $user, Exam $exam, array $bioData, string $language = 'te'): InterviewSession
    {
        // The allowance is one interview, and this is where it is spent. Enforced here in
        // the service rather than only in the screen, so no caller can route around it.
        $this->meter->assertWithinCaps($user, 'mock_interview');

        $session = InterviewSession::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'bio_data' => $bioData,
            'language' => $language,
            // Set explicitly rather than relying on the column default: a freshly created
            // model does not carry database defaults, and the first turn index is read
            // from this immediately.
            'question_count' => 0,
        ]);

        $this->askNext($session);

        return $session;
    }

    /**
     * Record an answer, give feedback on it, and ask the next question.
     *
     * The feedback and the next question are produced together in one call: the follow-up
     * has to press on what was just said, which means the same context that judged the
     * answer should be what chooses the next question. Two separate calls would cost twice
     * as much and produce a board that does not listen.
     */
    public function answer(InterviewSession $session, string $answer): AiStructured
    {
        $turn = $this->currentTurn($session);

        if ($turn === null) {
            return AiStructured::failed('no_open_question');
        }

        $result = $this->gateway->structured(
            instruction: $this->instruction($session),
            content: $this->transcriptFor($session, $turn, $answer),
            schema: $this->schema(),
            user: $session->user,
            feature: 'mock_interview',
            // The allowance was spent when this session opened. Charging it again per turn
            // would end the interview after the first question.
            enforceCap: false,
        );

        if (! $result->succeeded()) {
            return $result;
        }

        $turn->update([
            'answer' => $answer,
            'feedback' => $result->data['feedback'] ?? [],
        ]);

        if ((int) $session->question_count >= self::TARGET_QUESTIONS) {
            $this->finish($session, $result->data);

            return $result;
        }

        $this->recordQuestion(
            $session,
            (string) ($result->data['next_question'] ?? ''),
            (int) ($result->data['board_member'] ?? 1),
        );

        return $result;
    }

    public function currentTurn(InterviewSession $session): ?InterviewTurn
    {
        return InterviewTurn::query()
            ->where('interview_session_id', $session->id)
            ->whereNull('answer')
            ->orderBy('turn_index')
            ->first();
    }

    /**
     * The opening question.
     *
     * A board opens warm — somewhere the candidate cannot be wrong — because an interview
     * that starts with a hard question tells you only how someone handles being ambushed.
     */
    private function askNext(InterviewSession $session): void
    {
        $bio = $session->bio_data;
        $district = $bio['district'] ?? null;

        $opening = $district
            ? __('You are from :district. Tell the board about your district.', ['district' => $district])
            : __('Tell the board about yourself.');

        $this->recordQuestion($session, $opening, 1);
    }

    private function recordQuestion(InterviewSession $session, string $question, int $boardMember): void
    {
        if (trim($question) === '') {
            return;
        }

        InterviewTurn::create([
            'interview_session_id' => $session->id,
            'turn_index' => (int) ($session->question_count ?? 0),
            'board_member' => max(1, min(4, $boardMember)),
            'question' => $question,
        ]);

        $session->increment('question_count');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finish(InterviewSession $session, array $data): void
    {
        $session->update([
            'completed_at' => now(),
            'duration_sec' => $session->created_at->diffInSeconds(now()),
            'report' => [
                'summary' => $data['session_summary'] ?? null,
                'strengths' => $data['strengths'] ?? [],
                'work_on' => $data['work_on'] ?? [],
            ],
        ]);
    }

    private function transcriptFor(InterviewSession $session, InterviewTurn $turn, string $answer): string
    {
        $lines = [];

        foreach ($session->turns()->orderBy('turn_index')->get() as $previous) {
            $lines[] = 'Q: '.$previous->question;
            $lines[] = 'A: '.($previous->id === $turn->id ? $answer : ($previous->answer ?? '(no answer)'));
        }

        return implode("\n", $lines);
    }

    private function instruction(InterviewSession $session): string
    {
        $bio = $session->bio_data;

        $language = match ($session->language) {
            'en' => 'English',
            'mixed' => 'Telugu and English mixed, as a candidate would naturally answer',
            default => 'Telugu',
        };

        return implode("\n", array_filter([
            'You are a Group 1 interview board for an Indian state public service commission.',
            '',
            'The candidate bio-data, which is what a real board reads from:',
            isset($bio['district']) ? '- Home district: '.$bio['district'] : null,
            isset($bio['graduation']) ? '- Graduation subject: '.$bio['graduation'] : null,
            isset($bio['hobbies']) ? '- Hobbies as written on the form: '.$bio['hobbies'] : null,
            isset($bio['optional']) ? '- Optional subject: '.$bio['optional'] : null,
            '',
            'Ask in '.$language.'.',
            '',
            'Judge the last answer, then ask the next question.',
            // The behaviour that separates this from a question bank.
            'PREFER A FOLLOW-UP that presses on what the candidate just said, over a new',
            'topic. A board rarely accepts the first answer, and the follow-up is where a',
            'candidate is actually tested.',
            '',
            'Draw questions from the bio-data above. A candidate from a district should be',
            'asked about that district; an engineering graduate should be asked why they are',
            'here and not in industry. Generic questions are in every guidebook already.',
            '',
            'Feedback must be specific and short. "Too short for an interview — a board',
            'reads brevity as thin preparation" is useful. "Good answer" is not.',
            '',
            'Never score the candidate out of anything and never say whether they would be',
            'selected. A real board reads composure, body language and the file, and none of',
            'that is visible here.',
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
                'feedback' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ['good', 'warn']],
                            'note' => ['type' => 'string'],
                        ],
                    ],
                ],
                'next_question' => ['type' => 'string'],
                'board_member' => ['type' => 'integer'],
                'session_summary' => ['type' => 'string'],
                'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                'work_on' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['feedback', 'next_question'],
        ];
    }
}
