<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * The verdict of OutputGuard.
 *
 * `strippedDates` is surfaced rather than silently dropped. A non-empty array is the
 * clearest signal available that either the prompt or the corpus needs attention, and it
 * is logged against the request so the weekly review can act on it. Most confirmed-wrong
 * answers turn out to be missing source material, not model failure — which means the fix
 * is usually content, not prompting.
 */
final readonly class GuardedOutput
{
    /**
     * @param  list<string>  $strippedDates
     */
    private function __construct(
        public bool $blocked,
        public string $text = '',
        public float $confidence = 0.0,
        public ?string $reason = null,
        public array $strippedDates = [],
    ) {}

    /**
     * @param  list<string>  $strippedDates
     */
    public static function passed(string $text, float $confidence, array $strippedDates = []): self
    {
        return new self(
            blocked: false,
            text: $text,
            confidence: $confidence,
            strippedDates: $strippedDates,
        );
    }

    public static function blocked(string $reason): self
    {
        return new self(blocked: true, reason: $reason);
    }
}
