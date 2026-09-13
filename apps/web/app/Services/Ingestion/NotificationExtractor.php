<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Services\AI\Contracts\ModelClient;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns the unstructured text of a government notification into structured fields.
 *
 * "NEVER GUESS A DATE" IS LOAD-BEARING. A hallucinated deadline is the worst thing this
 * product can produce: a student reads it, plans around it, and misses the real one. The
 * instruction says so, and everything below it exists because an instruction is not enough:
 *
 *   - every date is re-validated after extraction and dropped if it is not a real,
 *     plausible date
 *   - every extracted date must actually appear in the source text, so a well-formatted
 *     invention is still discarded
 *   - the result always lands as `pending_review`, whatever the model returned
 *
 * The last one is the real safeguard. Extraction exists to save a reviewer typing, not to
 * replace them.
 */
final class NotificationExtractor
{
    public function __construct(
        private readonly ModelClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(string $text, string $sourceUrl): array
    {
        try {
            $raw = $this->client->extract(
                instruction: $this->instruction(),
                content: $text,
                schema: $this->schema(),
            );
        } catch (Throwable $e) {
            // Extraction failing is not a reason to lose the notification. A reviewer can
            // fill in a blank draft far faster than they can find the page again.
            report($e);

            return ['official_pdf_url' => str_ends_with($sourceUrl, '.pdf') ? $sourceUrl : null];
        }

        return $this->sanitise($raw->data, $text, $sourceUrl);
    }

    private function instruction(): string
    {
        return <<<'PROMPT'
            Extract structured data from this Indian government job notification.

            Rules that matter more than completeness:
            - NEVER guess a date. If a date is not clearly stated, return null.
            - The age reference date is the "as on" date stated in the notification,
              commonly 1 July. It is NOT the application deadline. If the text does not
              state one, return null rather than substituting the deadline.
            - Return ages as plain integers, fees as integers in rupees.
            - age_relaxation is years of relaxation by category, e.g. {"obc":3,"sc":5}.
            - allowed_states is a list of two-letter codes such as ["TS"], or null for
              an all-India recruitment. Do not infer a restriction from the issuing body.
            - min_qualification must be exactly one of: 10th, 12th, iti, diploma, degree,
              pg, btech, mbbs, phd.

            Anything you are unsure about must be null. A null is visibly missing to the
            person reviewing this; a guess looks like a fact and gets published.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => ['string', 'null']],
                'organisation' => ['type' => ['string', 'null']],
                'total_vacancies' => ['type' => ['integer', 'null']],
                'min_qualification' => ['type' => ['string', 'null']],
                'min_age' => ['type' => ['integer', 'null']],
                'max_age' => ['type' => ['integer', 'null']],
                'age_relaxation' => ['type' => ['object', 'null']],
                'age_reference_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'apply_start_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'apply_end_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'exam_date' => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
                'application_fee' => ['type' => ['object', 'null']],
                'salary_min' => ['type' => ['integer', 'null']],
                'salary_max' => ['type' => ['integer', 'null']],
                'allowed_states' => ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
                'official_pdf_url' => ['type' => ['string', 'null']],
            ],
        ];
    }

    /**
     * Everything the model returned, checked before it is trusted.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitise(array $raw, string $sourceText, string $sourceUrl): array
    {
        $out = [];

        foreach (['apply_start_date', 'apply_end_date', 'exam_date', 'age_reference_date'] as $field) {
            $out[$field] = $this->safeDate($raw[$field] ?? null, $sourceText);
        }

        // Age bounds that are outside any real recruitment are extraction noise.
        foreach (['min_age', 'max_age'] as $field) {
            $age = (int) ($raw[$field] ?? 0);
            $out[$field] = ($age >= 14 && $age <= 70) ? $age : null;
        }

        // An inverted range means the model mixed up two numbers. Better to drop both and
        // let a person read the PDF than to publish a range that excludes real candidates.
        if ($out['min_age'] && $out['max_age'] && $out['min_age'] > $out['max_age']) {
            $out['min_age'] = $out['max_age'] = null;
        }

        $qualifications = ['10th', '12th', 'iti', 'diploma', 'degree', 'pg', 'btech', 'mbbs', 'phd'];
        $qualification = is_string($raw['min_qualification'] ?? null) ? strtolower($raw['min_qualification']) : null;
        $out['min_qualification'] = in_array($qualification, $qualifications, true) ? $qualification : null;

        $vacancies = (int) ($raw['total_vacancies'] ?? 0);
        $out['total_vacancies'] = ($vacancies > 0 && $vacancies < 1_000_000) ? $vacancies : null;

        $out['organisation'] = is_string($raw['organisation'] ?? null) ? trim($raw['organisation']) : null;
        $out['title'] = is_string($raw['title'] ?? null) ? trim($raw['title']) : null;

        $out['age_relaxation'] = is_array($raw['age_relaxation'] ?? null) ? $raw['age_relaxation'] : null;
        $out['application_fee'] = is_array($raw['application_fee'] ?? null) ? $raw['application_fee'] : null;

        // Only real state codes. An invented one would silently hide the notification from
        // everyone, because the eligibility engine treats an unmatched state as a failure.
        $states = array_values(array_filter(
            (array) ($raw['allowed_states'] ?? []),
            static fn ($s): bool => in_array($s, ['TS', 'AP', 'KA', 'TN', 'MH', 'KL'], true),
        ));
        $out['allowed_states'] = $states ?: null;

        foreach (['salary_min', 'salary_max'] as $field) {
            $value = (int) ($raw[$field] ?? 0);
            $out[$field] = $value > 0 ? $value : null;
        }

        $pdf = $raw['official_pdf_url'] ?? null;
        $out['official_pdf_url'] = (is_string($pdf) && str_starts_with($pdf, 'http'))
            ? $pdf
            : (str_ends_with($sourceUrl, '.pdf') ? $sourceUrl : null);

        return array_filter($out, static fn ($v): bool => $v !== null);
    }

    /**
     * A date is kept only if it parses, is plausible, AND its digits appear in the source.
     *
     * The grounding check is the important half. A model that invents "15/03/2027" produces
     * something that parses perfectly well; the only thing distinguishing it from a real
     * date is whether it was actually written on the page.
     */
    private function safeDate(mixed $value, string $sourceText): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        // Recruitment notifications are not about 1970 or 2099.
        if ($date->year < 2020 || $date->year > (int) date('Y') + 3) {
            return null;
        }

        $sourceDigits = preg_replace('/\D/', '', $sourceText) ?? '';

        foreach ([$date->format('dmY'), $date->format('Ymd'), $date->format('dmy'), $date->format('jnY')] as $needle) {
            if (str_contains($sourceDigits, $needle)) {
                return $date->toDateString();
            }
        }

        // Parsed cleanly but appears nowhere in the source: treat as invented.
        return null;
    }
}
