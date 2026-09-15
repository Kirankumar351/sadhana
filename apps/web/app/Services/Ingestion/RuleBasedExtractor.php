<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use Carbon\CarbonImmutable;

/**
 * Facts read straight off the page, with no model involved.
 *
 * Government notifications state their key facts in a small number of fixed phrasings:
 * "Last Date & Time of submission of Online Application 22/08/2026", "the application
 * submission window will be opened from 09/10/2025 to 29/10/2025", "Closure of
 * registration of application 14/09/2026". Reading those phrases directly is cheaper and
 * safer than asking a model, because every value returned here was literally on the page
 * beside the label that gives it its meaning.
 *
 * DELIBERATELY NARROW. A value comes back only when a label says what it is. A bare date, an
 * age range inside a paragraph about relaxation, a number that could be a post code or a
 * vacancy count: all null. A null is visibly missing in the review queue; a wrong value looks
 * like a fact and gets published.
 *
 * This is also what keeps ingestion working with no AI key configured. The model improves on
 * this. It is not the only way a draft gets its dates.
 */
final class RuleBasedExtractor
{
    /** 22/08/2026, 01.07.2025, 25-08-26, 08-Sep-26, 25 August 2026. Always day first. */
    private const DATE = '(\d{1,2}[.\/-]\d{1,2}[.\/-](?:\d{4}|\d{2})|\d{1,2}[\s-](?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*[\s,-]+(?:\d{4}|\d{2}))';

    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    /**
     * @return array<string, mixed>
     */
    public function extract(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $d = self::DATE;
        $out = [];

        // An explicit window gives both ends at once and is the least ambiguous phrasing:
        // "window will be opened from 09/10/2025 to 29/10/2025", or APPSC's "Applications are
        // invited online through the Commission's Website (https://psc.ap.gov.in) from
        // eligible candidates from 16/10/2026 to 05/11/2026". The span may cross the dots in
        // a URL, but never an exam, test or interview, whose schedules use the same "from X
        // to Y" shape for dates nobody applies on.
        if (preg_match("/(?:window|applications?|registration|submission)\\b(?:(?!\\b(?:exam|examination|test|cbt|hall\\s*tickets?|interview)\\b).){0,220}?\\bfrom\\s+{$d}\\s*(?:to|till|upto|up to|–|-)\\s*{$d}/isu", $text, $m)) {
            $out['apply_start_date'] = $this->date($m[1]);
            $out['apply_end_date'] = $this->date($m[2]);
        }

        // Labels are followed by a colon, a hyphen, a full stop or nothing: RRB writes
        // "Opening date of Online application. 14.08.2026".
        $out['apply_start_date'] ??= $this->firstDate($text, [
            "/commencement of (?:online )?registration(?: of applications?)?\\s*[.:\\-]?\\s*{$d}/iu",
            "/submission of online applications?\\s*(?:starts?\\s*)?(?:from|on)\\s*[.:\\-]?\\s*{$d}/iu",
            "/\\bregistration\\s+from\\s+{$d}/iu",
            "/(?:start|opening) date (?:for|of) (?:the\\s*)?(?:submission\\s*of\\s*)?(?:online\\s*)?(?:applications?|registration)\\s*[.:\\-]?\\s*{$d}/iu",
        ]);

        $out['apply_end_date'] ??= $this->firstDate($text, [
            "/closure of registration(?: of applications?)?\\s*[.:\\-]?\\s*{$d}/iu",
            "/last date(?:\\s*(?:&|and)\\s*time)?\\s*(?:of|for|to)?\\s*(?:the\\s*)?(?:submission\\s*of\\s*)?(?:online\\s*)?(?:applications?|registration|applying|apply)?\\s*(?:is|[.:-])?\\s*{$d}/iu",
            "/closing date(?:\\s*(?:&|and)\\s*time)?\\s*(?:of|for)?\\s*(?:the\\s*)?(?:submission\\s*of\\s*)?(?:online\\s*)?(?:applications?|registration)?\\s*(?:is|[.:-])?\\s*{$d}/iu",
        ]);

        $out['notification_date'] = $this->firstDate($text, [
            "/notification\\s*no\\.?\\s*[\\w\\/.\\s-]{1,25}?,?\\s*dated?\\s*[:.,-]?\\s*{$d}/iu",
        ]);

        // "as on" also appears in "vacancies as on 09.09.2026", so it only counts as the age
        // reference date when the sentence is actually about age.
        $out['age_reference_date'] = $this->firstDate($text, [
            "/\\bage\\b[^.]{0,80}?\\bas on\\s+{$d}/iu",
        ]);

        if (preg_match('/\bage\s*limit\s*[:\-]?\s*(?:is\s*)?(\d{2})\s*(?:years?)?\s*(?:to|–|-)\s*(\d{2})\s*years/iu', $text, $m)) {
            $out['min_age'] = (int) $m[1];
            $out['max_age'] = (int) $m[2];
        }

        $out['min_age'] ??= $this->firstInt($text, ['/\bminimum age(?: limit)?\s*(?:of|is|:|-)?\s*(\d{2})\s*years/iu']);
        $out['max_age'] ??= $this->firstInt($text, [
            '/\bmaximum age(?: limit)?\s*(?:of|is|:|-)?\s*(\d{2})\s*years/iu',
            '/\bupper age limit\s*(?:of|is|:|-)?\s*(\d{2})\s*years/iu',
        ]);

        $out['total_vacancies'] = $this->firstInt($text, [
            // "for 10 vacancies". Not "apply for 2 posts only", which is a rule about
            // applicants, not a count of jobs.
            '/(?<!apply )(?<!only )\bfor\s+(\d{1,3}(?:,\d{3})+|\d{1,6})\s+(?:vacanc(?:y|ies)|posts)\b(?!\s+only)/iu',
            // A labelled total needs an explicit separator. In table text a bare heading sits
            // beside some other column's number: TGPSC's "Total No. of Vacancies 02 05 07"
            // reads as 2 when the real total is 7.
            '/\btotal\s*(?:no\.?\s*of\s*)?(?:vacanc(?:y|ies)|posts)\s*[:\-]\s*(\d{1,3}(?:,\d{3})+|\d{1,6})\b/iu',
        ]);

        return $this->consistent($out);
    }

    /**
     * Day first, always.
     *
     * Every Indian notification writes the day before the month. A parser allowed to guess
     * reads 09/10/2025 as the tenth of September, and the student misses the window by a
     * month.
     */
    public function date(string $raw): ?string
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $raw, $p)) {
            [$day, $month, $year] = [(int) $p[1], (int) $p[2], $this->year($p[3])];
        } elseif (preg_match('/^(\d{1,2})[\s-]([a-z]{3,9})[\s,-]+(\d{2}|\d{4})$/i', $raw, $p)) {
            $index = array_search(strtolower(substr($p[2], 0, 3)), self::MONTHS, true);

            if ($index === false) {
                return null;
            }

            [$day, $month, $year] = [(int) $p[1], $index + 1, $this->year($p[3])];
        } else {
            return null;
        }

        if (! checkdate($month, $day, $year) || $year < 2020 || $year > (int) date('Y') + 3) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->toDateString();
    }

    /**
     * Drop anything that contradicts itself.
     *
     * A window that closes before it opens, or an age range that runs backwards, means two
     * numbers were read from the wrong places. Publishing either would be worse than a gap.
     *
     * @param  array<string, mixed>  $out
     * @return array<string, mixed>
     */
    private function consistent(array $out): array
    {
        if (isset($out['apply_start_date'], $out['apply_end_date']) && $out['apply_start_date'] > $out['apply_end_date']) {
            unset($out['apply_start_date'], $out['apply_end_date']);
        }

        foreach (['min_age', 'max_age'] as $field) {
            if (isset($out[$field]) && ($out[$field] < 14 || $out[$field] > 70)) {
                $out[$field] = null;
            }
        }

        if (isset($out['min_age'], $out['max_age']) && $out['min_age'] > $out['max_age']) {
            unset($out['min_age'], $out['max_age']);
        }

        if (isset($out['total_vacancies']) && $out['total_vacancies'] < 1) {
            $out['total_vacancies'] = null;
        }

        return array_filter($out, static fn ($value): bool => $value !== null);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function firstDate(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) && ($date = $this->date($m[1])) !== null) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function firstInt(string $text, array $patterns): ?int
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return (int) str_replace(',', '', $m[1]);
            }
        }

        return null;
    }

    private function year(string $digits): int
    {
        return strlen($digits) === 2 ? 2000 + (int) $digits : (int) $digits;
    }
}
