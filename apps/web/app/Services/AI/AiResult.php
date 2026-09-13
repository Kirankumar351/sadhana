<?php

declare(strict_types=1);

namespace App\Services\AI;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\Wireable;

/**
 * The result of any AI call.
 *
 * Every path through AiGateway returns one of these — including refusals, cap hits and
 * outright failures. Nothing throws at the UI layer, because an AI surface must degrade
 * gracefully: the page it sits on is still the page a student came for.
 *
 * `status` is what the UI renders on. Note that `handed_off`, `no_sources` and
 * `low_confidence` are not errors — they are the product working correctly. A visible
 * "I don't know, ask the community" builds more trust than a fluent wrong answer.
 */
final readonly class AiResult implements Wireable
{
    public const ANSWERED = 'answered';

    public const HANDED_OFF = 'handed_off';       // routed to a deterministic engine

    public const NO_SOURCES = 'no_sources';       // nothing in the corpus to answer from

    public const LOW_CONFIDENCE = 'low_confidence';

    public const BLOCKED = 'blocked';             // an output guard fired

    public const CAP_REACHED = 'cap_reached';

    public const UNAVAILABLE = 'unavailable';     // provider failure

    /**
     * @param  list<RetrievedPassage>  $sources
     */
    private function __construct(
        public string $status,
        public string $text = '',
        public array $sources = [],
        public float $confidence = 0.0,
        public ?string $promptVersion = null,
        public ?string $handler = null,
        public ?string $reason = null,
        public bool $fromCache = false,
        public ?CarbonInterface $resetsAt = null,
        public ?int $used = null,
        public ?int $limit = null,
    ) {}

    /**
     * @param  list<RetrievedPassage>  $sources
     */
    public static function answered(
        string $text,
        array $sources,
        float $confidence,
        ?string $promptVersion = null,
    ): self {
        return new self(
            status: self::ANSWERED,
            text: $text,
            sources: $sources,
            confidence: $confidence,
            promptVersion: $promptVersion,
        );
    }

    public static function fromCache(self $cached): self
    {
        return new self(
            status: $cached->status,
            text: $cached->text,
            sources: $cached->sources,
            confidence: $cached->confidence,
            promptVersion: $cached->promptVersion,
            fromCache: true,
        );
    }

    public static function handedOff(string $handler, string $reason): self
    {
        return new self(status: self::HANDED_OFF, handler: $handler, reason: $reason);
    }

    public static function noSources(): self
    {
        return new self(status: self::NO_SOURCES, reason: 'no_sources');
    }

    /**
     * @param  list<RetrievedPassage>  $sources
     */
    public static function lowConfidence(float $confidence, array $sources = []): self
    {
        return new self(
            status: self::LOW_CONFIDENCE,
            sources: $sources,
            confidence: $confidence,
            reason: 'below_confidence',
        );
    }

    public static function blocked(string $reason): self
    {
        return new self(status: self::BLOCKED, reason: $reason);
    }

    public static function capReached(CarbonInterface $resetsAt, int $used, int $limit): self
    {
        return new self(
            status: self::CAP_REACHED,
            reason: 'cap_reached',
            resetsAt: $resetsAt,
            used: $used,
            limit: $limit,
        );
    }

    public static function unavailable(): self
    {
        return new self(status: self::UNAVAILABLE, reason: 'provider_unavailable');
    }

    public function isAnswer(): bool
    {
        return $this->status === self::ANSWERED;
    }

    /**
     * Whether the UI should offer "ask the community" instead. All three of these states
     * mean the same thing to a student: a person will answer this better than we can.
     */
    public function shouldOfferCommunity(): bool
    {
        return in_array(
            $this->status,
            [self::NO_SOURCES, self::LOW_CONFIDENCE, self::UNAVAILABLE],
            true,
        );
    }

    /**
     * Livewire serialisation.
     *
     * WITHOUT THIS, EVERY AI SCREEN IN THE PRODUCT IS BROKEN. A component holding an
     * AiResult renders correctly the first time and then throws "property type not
     * supported" the moment Livewire dehydrates it for the response — so the answer never
     * reaches the browser. A readonly value object with a private constructor cannot be
     * rebuilt by Livewire on its own, so it says how.
     *
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return [
            'status' => $this->status,
            'text' => $this->text,
            'sources' => array_map(
                static fn (RetrievedPassage $passage): array => $passage->toLivewire(),
                $this->sources,
            ),
            'confidence' => $this->confidence,
            'promptVersion' => $this->promptVersion,
            'handler' => $this->handler,
            'reason' => $this->reason,
            'fromCache' => $this->fromCache,
            'resetsAt' => $this->resetsAt?->toIso8601String(),
            'used' => $this->used,
            'limit' => $this->limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): self
    {
        return new self(
            status: (string) ($value['status'] ?? self::UNAVAILABLE),
            text: (string) ($value['text'] ?? ''),
            sources: array_map(
                static fn (array $passage): RetrievedPassage => RetrievedPassage::fromLivewire($passage),
                $value['sources'] ?? [],
            ),
            confidence: (float) ($value['confidence'] ?? 0.0),
            promptVersion: $value['promptVersion'] ?? null,
            handler: $value['handler'] ?? null,
            reason: $value['reason'] ?? null,
            fromCache: (bool) ($value['fromCache'] ?? false),
            resetsAt: isset($value['resetsAt']) ? Carbon::parse($value['resetsAt']) : null,
            used: isset($value['used']) ? (int) $value['used'] : null,
            limit: isset($value['limit']) ? (int) $value['limit'] : null,
        );
    }
}
