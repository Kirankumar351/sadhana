<?php

declare(strict_types=1);

namespace App\Services\AI;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Livewire\Wireable;

/**
 * The result of a structured AI call.
 *
 * Like AiResult, nothing here throws at the UI: a cap hit and a provider failure are both
 * states a screen renders, not exceptions it handles. Evaluation and interview are premium
 * features, and a paying user meeting a stack trace is worse than one meeting a clear
 * "we could not read that, your credit has not been used".
 */
final readonly class AiStructured implements Wireable
{
    public const OK = 'ok';

    public const FAILED = 'failed';

    public const CAP_REACHED = 'cap_reached';

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public string $status,
        public array $data = [],
        public ?string $reason = null,
        public ?CarbonInterface $resetsAt = null,
        public ?int $used = null,
        public ?int $limit = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data): self
    {
        return new self(status: self::OK, data: $data);
    }

    public static function failed(string $reason): self
    {
        return new self(status: self::FAILED, reason: $reason);
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

    public function succeeded(): bool
    {
        return $this->status === self::OK;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return [
            'status' => $this->status,
            'data' => $this->data,
            'reason' => $this->reason,
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
            status: (string) ($value['status'] ?? self::FAILED),
            data: (array) ($value['data'] ?? []),
            reason: $value['reason'] ?? null,
            resetsAt: isset($value['resetsAt']) ? Carbon::parse($value['resetsAt']) : null,
            used: isset($value['used']) ? (int) $value['used'] : null,
            limit: isset($value['limit']) ? (int) $value['limit'] : null,
        );
    }
}
