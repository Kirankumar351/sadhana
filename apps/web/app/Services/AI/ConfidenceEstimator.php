<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A confidence estimate, since no provider returns one.
 *
 * Shared by every model client so that Claude and Gemini are judged by the same rule. A
 * hedge phrase added for one provider and not the other would let the weaker answer
 * through on exactly the provider nobody was watching.
 *
 * Deliberately crude and deliberately pessimistic. It combines retrieval strength with
 * whether the model hedged, and it only ever REDUCES confidence — an answer that says "I
 * could not find" must fall below the floor and hand over to the community, however fluent
 * it reads.
 */
final class ConfidenceEstimator
{
    private const HEDGES = [
        'i could not find', 'i do not have', 'not in the passages', 'unclear',
        'నా దగ్గర లేదు', 'కనుగొనలేకపోయాను',
    ];

    /**
     * @param  list<RetrievedPassage>  $passages
     */
    public function estimate(string $text, array $passages, bool $truncated): float
    {
        // No text is no answer. Gemini can spend its whole output budget thinking and
        // return nothing; that must never read as a confident empty reply.
        if (trim($text) === '') {
            return 0.0;
        }

        $scores = array_map(static fn (RetrievedPassage $p): float => $p->score, $passages);
        $retrieval = $scores === [] ? 0.0 : max($scores);
        $lower = mb_strtolower($text);

        foreach (self::HEDGES as $hedge) {
            if (str_contains($lower, $hedge)) {
                return 0.2;
            }
        }

        // A truncated answer is an incomplete answer, whatever its content.
        if ($truncated) {
            return min($retrieval, 0.5);
        }

        return round(min($retrieval, 1.0), 3);
    }
}
