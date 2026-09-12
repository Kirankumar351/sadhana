<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Routes questions away from the model when a model must not answer them.
 *
 * This is the first hard block in the AI layer and the most important one. Two categories
 * never reach a language model, no matter how it is asked:
 *
 *   ELIGIBILITY   "am I eligible for the constable job?"
 *                 Answered by EligibilityService, deterministically, from the user's own
 *                 stored profile against verified criteria. A model guessing here would be
 *                 telling someone whether to spend a year of their life on an exam.
 *
 *   CUTOFF PREDICTION  "what will the cutoff be this year?"
 *                 Refused, and five years of real cutoffs shown instead. A cutoff depends
 *                 on vacancy count, paper difficulty and how many people sat the exam. A
 *                 confident guess makes someone plan wrongly.
 *
 * Detection is bilingual and intentionally over-broad. A false positive costs a routed
 * question that gets a better, deterministic answer. A false negative costs a hallucinated
 * eligibility outcome. The asymmetry is not close.
 */
final class IntentRouter
{
    /**
     * English and Telugu markers. Telugu is matched on stems rather than whole words
     * because the language is agglutinative and the suffix varies with the question form.
     *
     * @var list<string>
     */
    private const ELIGIBILITY_MARKERS = [
        // English
        'am i eligible', 'can i apply', 'do i qualify', 'eligible for', 'my eligibility',
        'age limit for me', 'can i write', 'am i allowed',
        // Telugu
        'నేను అర్హుడ', 'నేను అర్హుర', 'అర్హత ఉంద', 'దరఖాస్తు చేయవచ్చ',
        'నేను రాయవచ్చ', 'నాకు అర్హత',
    ];

    /** @var list<string> */
    private const CUTOFF_PREDICTION_MARKERS = [
        // English
        'what will the cutoff', 'cutoff prediction', 'expected cutoff', 'predict cutoff',
        'how much cutoff', 'cutoff this year', 'will i get selected', 'my chances',
        'safe score', 'will i clear',
        // Telugu
        'కటాఫ్ ఎంత', 'కటాఫ్ ఎంతొస్త', 'కటాఫ్ ఎంత వస్త', 'సెలెక్ట్ అవుతాన',
        'నా చాన్స్', 'క్లియర్ అవుతాన',
    ];

    /** @var list<string> */
    private const DATE_FEE_MARKERS = [
        'last date', 'apply end date', 'fee for', 'application fee',
        'చివరి తేదీ', 'ఫీజు ఎంత', 'దరఖాస్తు తేదీ',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function route(string $question, array $context = []): IntentRoute
    {
        $normalised = $this->normalise($question);

        if ($this->matches($normalised, self::ELIGIBILITY_MARKERS)) {
            return IntentRoute::deterministic(
                handler: 'eligibility_engine',
                reason: 'eligibility_routed',
            );
        }

        if ($this->matches($normalised, self::CUTOFF_PREDICTION_MARKERS)) {
            return IntentRoute::deterministic(
                handler: 'cutoff_history',
                reason: 'cutoff_prediction_refused',
            );
        }

        /**
         * Dates and fees are different from the two above: the model MAY state one, but
         * only by quoting a retrieved passage verbatim, and OutputGuard strips any date
         * not present in the passages. So this is not a hard route — it is a flag that
         * makes the guard strict for this request.
         */
        if ($this->matches($normalised, self::DATE_FEE_MARKERS)) {
            return IntentRoute::model(strictFactMode: true);
        }

        return IntentRoute::model();
    }

    private function normalise(string $question): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $question) ?? $question));
    }

    /**
     * @param  list<string>  $markers
     */
    private function matches(string $haystack, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($haystack, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
    }
}
