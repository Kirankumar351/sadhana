<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

/**
 * A hard block fired.
 *
 * These are the rules that cannot be turned off from the admin panel: no eligibility
 * outcomes, no invented dates, no cutoff prediction, no selection guarantees, no answering
 * without sources, no reproduction of copyrighted material.
 *
 * Thrown rather than returned when the violation is structural — a feature asking the
 * gateway to do something it must never do — as opposed to a model merely producing bad
 * output, which OutputGuard handles by returning a blocked verdict.
 */
final class GuardrailException extends RuntimeException
{
    public static function eligibilityOutcome(): self
    {
        return new self('no_eligibility_outcomes');
    }

    public static function cutoffPrediction(): self
    {
        return new self('no_cutoff_prediction');
    }

    public static function missingSources(): self
    {
        return new self('no_answer_without_sources');
    }

    public static function featureDisabled(string $feature): self
    {
        return new self("feature_disabled:{$feature}");
    }
}
