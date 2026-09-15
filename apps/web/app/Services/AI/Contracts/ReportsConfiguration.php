<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

/**
 * A provider client that can say whether a call could possibly succeed.
 *
 * Separate from ModelClient and EmbeddingClient on purpose. Callers check it up front so an
 * unconfigured provider is treated as a deployment state rather than discovered as an
 * exception on every scraped item or saved notification — and test doubles, which have
 * nothing to configure, simply do not implement it and are always treated as available.
 */
interface ReportsConfiguration
{
    public function isConfigured(): bool;
}
