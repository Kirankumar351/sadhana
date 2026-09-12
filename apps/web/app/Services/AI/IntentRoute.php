<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Where IntentRouter decided a question should go.
 *
 * `strictFactMode` is not a route away from the model — it is a signal that the question
 * asked for a date or a fee, so OutputGuard should be unforgiving about any figure that
 * is not present verbatim in the retrieved passages.
 */
final readonly class IntentRoute
{
    private function __construct(
        public bool $toModel,
        public ?string $handler = null,
        public ?string $reason = null,
        public bool $strictFactMode = false,
    ) {}

    public static function model(bool $strictFactMode = false): self
    {
        return new self(toModel: true, strictFactMode: $strictFactMode);
    }

    public static function deterministic(string $handler, string $reason): self
    {
        return new self(toModel: false, handler: $handler, reason: $reason);
    }

    public function isDeterministic(): bool
    {
        return ! $this->toModel;
    }
}
