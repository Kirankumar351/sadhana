<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamNotification;
use App\Models\Profile;
use Carbon\CarbonImmutable;

/**
 * The eligibility engine.
 *
 * This is the single highest-leverage piece of code in the product: it converts a notice
 * board into an advisor, and it is the one thing no competitor does. It is also the code
 * with the highest cost of being wrong, because a person reads its output and decides
 * whether to spend a year of their life on an exam.
 *
 * THREE RULES THAT DO NOT BEND:
 *
 * 1. This is DETERMINISTIC. No model is ever consulted. The AI intent router exists
 *    precisely to route eligibility questions here instead of to an LLM.
 *
 * 2. `partial` is a real answer, not a rounding of "no". A user who fails only on age
 *    still wants to see the notification — they may hold a relaxation we do not know
 *    about, or be reading it for a sibling. Never hide content; label it.
 *
 * 3. The result is GUIDANCE, not a legal determination. Every rendered badge carries
 *    "please verify against the official notification before applying".
 *
 * Target test coverage is 95%. See tests/Unit/EligibilityServiceTest.php.
 */
final class EligibilityService
{
    public const STATUS_ELIGIBLE = 'eligible';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_NOT_ELIGIBLE = 'not_eligible';
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Qualification ladder. Higher rank satisfies a lower requirement.
     *
     * B.Tech and a general degree sit at the same rank deliberately: a notification asking
     * for "any degree" is satisfied by either, and one asking specifically for B.Tech is
     * expressed through `qualification_notes`, which a human reads. Encoding stream rules
     * numerically here would produce confident wrong answers.
     */
    private const QUALIFICATION_RANK = [
        '10th' => 1,
        '12th' => 2,
        'iti' => 2,
        'diploma' => 3,
        'degree' => 4,
        'btech' => 4,
        'mbbs' => 5,
        'pg' => 5,
        'phd' => 6,
    ];

    /**
     * @return array{
     *     status: string,
     *     matched: list<array{key: string, label: string}>,
     *     failed: list<array{key: string, label: string, reason: string}>,
     *     score: float,
     *     effective_max_age: int|null,
     *     caveat: string
     * }
     */
    public function check(ExamNotification $notification, ?Profile $profile): array
    {
        if (! $profile instanceof Profile) {
            return $this->unknown();
        }

        $matched = [];
        $failed = [];
        $effectiveMaxAge = null;

        $this->checkQualification($notification, $profile, $matched, $failed);
        $effectiveMaxAge = $this->checkAge($notification, $profile, $matched, $failed);
        $this->checkDomicile($notification, $profile, $matched, $failed);
        $this->checkGender($notification, $profile, $matched, $failed);

        $total = count($matched) + count($failed);

        // No criteria to test against means we genuinely do not know. Saying "eligible"
        // here would be the worst possible default — it is the answer people act on.
        if ($total === 0) {
            return $this->unknown();
        }

        $score = count($matched) / $total;

        $status = match (true) {
            $failed === [] => self::STATUS_ELIGIBLE,
            $score >= 0.5 => self::STATUS_PARTIAL,
            default => self::STATUS_NOT_ELIGIBLE,
        };

        return [
            'status' => $status,
            'matched' => $matched,
            'failed' => $failed,
            'score' => round($score, 3),
            'effective_max_age' => $effectiveMaxAge,
            'caveat' => __('Please verify against the official notification before applying.'),
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $matched
     * @param  list<array{key: string, label: string, reason: string}>  $failed
     */
    private function checkQualification(
        ExamNotification $notification,
        Profile $profile,
        array &$matched,
        array &$failed,
    ): void {
        if ($notification->min_qualification === null) {
            return;
        }

        // A profile with no qualification recorded cannot be tested. Counting it as a
        // failure would tell a user they are ineligible because of something they simply
        // have not told us yet.
        if ($profile->highest_qualification === null) {
            return;
        }

        $required = self::QUALIFICATION_RANK[$notification->min_qualification] ?? 0;
        $held = self::QUALIFICATION_RANK[$profile->highest_qualification] ?? 0;

        if ($held >= $required) {
            $matched[] = ['key' => 'qualification', 'label' => __('Qualification')];

            return;
        }

        $failed[] = [
            'key' => 'qualification',
            'label' => __('Qualification'),
            'reason' => __('Requires :q', ['q' => __($notification->min_qualification)]),
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $matched
     * @param  list<array{key: string, label: string, reason: string}>  $failed
     * @return int|null the maximum age after relaxation, for display
     */
    private function checkAge(
        ExamNotification $notification,
        Profile $profile,
        array &$matched,
        array &$failed,
    ): ?int {
        if ($profile->date_of_birth === null) {
            return null;
        }

        if ($notification->min_age === null && $notification->max_age === null) {
            return null;
        }

        $age = $this->ageOnReferenceDate($notification, $profile);

        $relaxation = $this->ageRelaxationFor($notification, $profile);
        $effectiveMax = $notification->max_age !== null
            ? $notification->max_age + $relaxation
            : null;

        $meetsMin = $notification->min_age === null || $age >= $notification->min_age;
        $meetsMax = $effectiveMax === null || $age <= $effectiveMax;

        if ($meetsMin && $meetsMax) {
            $matched[] = ['key' => 'age', 'label' => __('Age')];

            return $effectiveMax;
        }

        $failed[] = [
            'key' => 'age',
            'label' => __('Age'),
            'reason' => __('Age limit :min-:max', [
                'min' => $notification->min_age ?? 18,
                'max' => $effectiveMax ?? '-',
            ]),
        ];

        return $effectiveMax;
    }

    /**
     * Completed years as on the notification's reference date.
     *
     * The reference date matters more than anything else in this method. Indian government
     * notifications compute age "as on" a stated cut-off — commonly 1 July — and NOT as on
     * the application deadline. Using the wrong one shifts a boundary candidate across the
     * line in either direction. Fall back to the deadline only when no reference date was
     * recorded, and to today only when neither exists.
     */
    private function ageOnReferenceDate(ExamNotification $notification, Profile $profile): int
    {
        $reference = CarbonImmutable::parse(
            $notification->age_reference_date
                ?? $notification->apply_end_date
                ?? CarbonImmutable::now()
        )->startOfDay();

        $birth = CarbonImmutable::parse($profile->date_of_birth)->startOfDay();

        // Completed years only. Someone who turns 22 tomorrow is 21 today, and an exam
        // with a maximum age of 21 accepts them.
        return (int) $birth->diffInYears($reference, absolute: false);
    }

    /**
     * Relaxations stack: a PwD candidate from a reserved category receives both.
     * Ex-servicemen relaxation is expressed differently across notifications, which is why
     * it is read from the recorded `age_relaxation` JSON rather than assumed here.
     */
    private function ageRelaxationFor(ExamNotification $notification, Profile $profile): int
    {
        /** @var array<string, int> $rules */
        $rules = $notification->age_relaxation ?? [];

        $relaxation = 0;

        if ($profile->category !== null) {
            $relaxation += (int) ($rules[$profile->category] ?? 0);
        }

        if ($profile->is_pwd) {
            $relaxation += (int) ($rules['pwd'] ?? 0);
        }

        if ($profile->is_ex_serviceman) {
            $relaxation += (int) ($rules['ex_serviceman'] ?? 0);
        }

        return $relaxation;
    }

    /**
     * @param  list<array{key: string, label: string}>  $matched
     * @param  list<array{key: string, label: string, reason: string}>  $failed
     */
    private function checkDomicile(
        ExamNotification $notification,
        Profile $profile,
        array &$matched,
        array &$failed,
    ): void {
        $allowed = $notification->allowed_states;

        // Null or empty means all-India. Not "no states allowed".
        if (empty($allowed)) {
            return;
        }

        if (in_array($profile->state, $allowed, true)) {
            $matched[] = ['key' => 'state', 'label' => __('State')];

            return;
        }

        $failed[] = [
            'key' => 'state',
            'label' => __('State'),
            'reason' => __('Only for :states', ['states' => implode(', ', $allowed)]),
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $matched
     * @param  list<array{key: string, label: string, reason: string}>  $failed
     */
    private function checkGender(
        ExamNotification $notification,
        Profile $profile,
        array &$matched,
        array &$failed,
    ): void {
        if ($notification->gender_restriction === 'any' || $profile->gender === null) {
            return;
        }

        if ($notification->gender_restriction === $profile->gender) {
            $matched[] = ['key' => 'gender', 'label' => __('Gender')];

            return;
        }

        $failed[] = [
            'key' => 'gender',
            'label' => __('Gender'),
            'reason' => __('Not applicable'),
        ];
    }

    /**
     * @return array{status: string, matched: list<array{key: string, label: string}>, failed: list<array{key: string, label: string, reason: string}>, score: float, effective_max_age: int|null, caveat: string}
     */
    private function unknown(): array
    {
        return [
            'status' => self::STATUS_UNKNOWN,
            'matched' => [],
            'failed' => [],
            'score' => 0.0,
            'effective_max_age' => null,
            'caveat' => __('Add your details to see if you qualify.'),
        ];
    }
}
