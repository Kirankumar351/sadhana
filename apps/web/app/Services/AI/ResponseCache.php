<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Content-keyed response cache.
 *
 * KEYED BY CONTENT, NEVER BY USER. This is the single decision that makes the AI layer
 * affordable. "Explain this passage" has the same correct answer for every student who
 * selects that passage, so one generation serves thousands — which is why Explain costs
 * about Rs 0.008 a call against Rs 0.074 for a doubt, and most of the reason the whole
 * layer fits inside Rs 0.40 per active user per month.
 *
 * Two tiers: Redis for speed, and the `ai_cache` table for durability and for the hit
 * counts that tell us which content to pre-warm. A Redis flush costs latency, not money,
 * because the database tier survives it.
 *
 * The prompt version is part of the key. Editing a prompt must not keep serving answers
 * produced by the previous one — that is how a fix appears not to have worked.
 */
final class ResponseCache
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function keyFor(string $feature, string $input, string $locale, array $context = []): string
    {
        // Only scope-defining context belongs in the key. Including the user id here would
        // silently defeat the entire point of this class.
        ksort($context);

        $scope = array_intersect_key($context, array_flip([
            'notification_id', 'exam_id', 'material_id', 'passage_hash', 'topic', 'depth',
        ]));

        return hash('sha256', implode('|', [
            $feature,
            $locale,
            $this->promptVersion($feature),
            $this->normalise($input),
            json_encode($scope, JSON_THROW_ON_ERROR),
        ]));
    }

    public function get(string $key): ?AiResult
    {
        $cached = Cache::get("ai:resp:{$key}");

        if ($cached instanceof AiResult) {
            return $cached;
        }

        $row = AiCache::query()
            ->where('cache_key', $key)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($row === null) {
            return null;
        }

        $row->incrementQuietly('hits');

        /** @var AiResult|null $result */
        $result = unserialize($row->response, ['allowed_classes' => [
            AiResult::class,
            RetrievedPassage::class,
        ]]);

        if (! $result instanceof AiResult) {
            return null;
        }

        Cache::put("ai:resp:{$key}", $result, now()->addHours(6));

        return $result;
    }

    public function put(string $key, AiResult $result, string $feature): void
    {
        // Never cache a refusal, a low-confidence hand-off or a provider failure. Those are
        // transient states, and caching one would freeze a temporary problem into a
        // permanent wrong answer for everyone who asks the same thing.
        if (! $result->isAnswer()) {
            return;
        }

        $ttl = (int) config("ai.cache_ttl.{$feature}", 3600);
        $expiresAt = CarbonImmutable::now()->addSeconds($ttl);

        AiCache::updateOrCreate(
            ['cache_key' => $key],
            [
                'response' => serialize($result),
                'locale' => app()->getLocale(),
                'feature' => $feature,
                'expires_at' => $expiresAt,
            ],
        );

        Cache::put("ai:resp:{$key}", $result, min($ttl, 60 * 60 * 6));
    }

    public function forgetFeature(string $feature): void
    {
        AiCache::query()->where('feature', $feature)->delete();
    }

    /**
     * Whitespace and case are not meaningful; two students asking the same question with
     * different spacing should hit the same cache entry.
     */
    private function normalise(string $input): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $input) ?? $input));
    }

    private function promptVersion(string $feature): string
    {
        return (string) Cache::remember(
            "ai:promptver:{$feature}",
            now()->addMinutes(5),
            static fn (): string => (string) config("ai.features.{$feature}.version", 'v1'),
        );
    }
}
