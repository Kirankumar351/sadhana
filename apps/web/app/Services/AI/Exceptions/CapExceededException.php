<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * A per-user AI cap was reached.
 *
 * Caught by AiGateway and turned into an AiResult, never surfaced as a 500. The limit
 * exists so the assistant stays free for everyone: without it, one user can cost what a
 * thousand do. The message the student sees says exactly that, and points out that
 * everything else on the platform still works — only the chat assistant is capped.
 */
final class CapExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        public readonly int $used,
        public readonly int $limit,
        public readonly CarbonInterface $resetsAt,
        public readonly string $window = 'day',
    ) {
        parent::__construct("AI cap reached for {$feature}: {$used}/{$limit} per {$window}");
    }
}
