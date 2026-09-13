<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiRequest;
use App\Models\InterviewSession;
use App\Models\User;
use App\Services\AI\Exceptions\CapExceededException;
use App\Services\Billing\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Cost accounting and per-user caps.
 *
 * Integration Map gap 4: the guardrails screen sets "30 questions a day" and "30 notes a
 * month", but nothing in the original schema counted them, so the cap could not actually
 * be enforced. Counting is done from `ai_requests` against the index
 * (user_id, feature, created_at), with the running total held in Redis so the hot path
 * does not hit MySQL on every question.
 *
 * The Redis counter is an optimisation, not the source of truth. It is seeded from the
 * database on a miss, so a Redis flush costs one extra query rather than an unlimited
 * spending window — which is the failure mode that matters.
 */
final class CostMeter
{
    public function __construct(
        private readonly FeatureGate $gate,
    ) {}

    /**
     * @throws CapExceededException
     */
    public function assertWithinCaps(?User $user, string $feature): void
    {
        // Anonymous usage is capped by IP at the middleware layer, not here.
        if (! $user instanceof User) {
            return;
        }

        [$limit, $window] = $this->limitFor($user, $feature);

        if ($limit === null) {
            return;
        }

        $used = $this->usage($user, $feature, $window);

        if ($used >= $limit) {
            throw new CapExceededException(
                feature: $feature,
                used: $used,
                limit: $limit,
                resetsAt: $this->resetsAt($window),
                window: $window,
            );
        }
    }

    /**
     * Returns [limit, window]. A null limit means uncapped.
     *
     * Which cap applies is an entitlement question, not a plan question — `ai_higher_caps`
     * can arrive from a subscription, a coupon, a campus deal or a manual grant, and this
     * code does not need to know which.
     *
     * @return array{0: int|null, 1: string}
     */
    private function limitFor(User $user, string $feature): array
    {
        $tier = $this->gate->allows($user, 'ai_higher_caps') ? 'premium' : 'free';

        return match ($feature) {
            'ask', 'doubt_solver' => [config("ai.caps.{$tier}.ask_questions_per_day"), 'day'],
            'notes' => [config("ai.caps.{$tier}.notes_per_month"), 'month'],
            'flashcards' => [config("ai.caps.{$tier}.flashcard_decks_per_month"), 'month'],
            'answer_eval' => [config("ai.caps.{$tier}.answer_evaluations_per_month"), 'month'],
            'mock_interview' => [config("ai.caps.{$tier}.mock_interviews_per_month"), 'month'],

            // Explain is 92% cache-hit and costs a fraction of a paisa. Capping it would
            // save nothing and break the one AI feature that is genuinely always available.
            'explain' => [null, 'day'],

            // Admin pipelines are budgeted at the organisation level, not per user.
            default => [null, 'day'],
        };
    }

    public function usage(User $user, string $feature, string $window = 'day'): int
    {
        /**
         * A MOCK INTERVIEW IS ONE UNIT, NOT TWENTY.
         *
         * Every turn of an interview is its own metered call — it has to be, or the cost of
         * the most expensive feature in the product would be invisible. But the cap the
         * student was told about is "one mock interview a month", and counting requests
         * would spend it on the first question and end the interview there.
         *
         * So the cost is metered per turn and the allowance is counted per session. The
         * two numbers answer different questions and it is correct for them to differ.
         */
        if ($feature === 'mock_interview') {
            return InterviewSession::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>=', $this->windowStart($window))
                ->count();
        }

        $key = $this->counterKey($user, $feature, $window);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return (int) $cached;
        }

        $count = AiRequest::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->where('created_at', '>=', $this->windowStart($window))
            ->where('cache_hit', false)   // a cache hit costs nothing, so it does not count
            ->whereNull('refused_reason') // a refusal costs nothing either
            ->count();

        Cache::put($key, $count, $this->resetsAt($window));

        return $count;
    }

    /**
     * @param  list<RetrievedPassage>  $sources
     */
    public function record(
        ?User $user,
        string $feature,
        string $model,
        ?string $promptVersion,
        int $inputTokens,
        int $outputTokens,
        float $confidence,
        array $sources,
        int $latencyMs,
    ): void {
        AiRequest::create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'model' => $model,
            'prompt_version' => $promptVersion,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_paise' => $this->priceFor($model, $inputTokens, $outputTokens),
            'confidence' => $confidence,
            'sources_used' => array_map(
                static fn (RetrievedPassage $p): array => $p->toCitation(),
                $sources,
            ),
            'cache_hit' => false,
            'latency_ms' => $latencyMs,
        ]);

        if ($user instanceof User) {
            $this->bump($user, $feature);
        }
    }

    public function recordCacheHit(?User $user, string $feature): void
    {
        AiRequest::create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'model' => 'cache',
            'cost_paise' => 0,
            'cache_hit' => true,
        ]);
    }

    /**
     * Refusals are recorded deliberately and reviewed weekly.
     *
     * When 1,842 people ask what the cutoff will be and every one of them gets real
     * historical data instead of a guess, that refusal count is the product working — it is
     * the single clearest evidence the guardrails are earning their place.
     */
    public function recordRefusal(?User $user, string $feature, string $reason): void
    {
        AiRequest::create([
            'user_id' => $user?->id,
            'feature' => $feature,
            'model' => 'none',
            'cost_paise' => 0,
            'refused_reason' => $reason,
        ]);
    }

    private function bump(User $user, string $feature): void
    {
        foreach (['day', 'month'] as $window) {
            $key = $this->counterKey($user, $feature, $window);

            if (Cache::has($key)) {
                Cache::increment($key);
            }
        }
    }

    /**
     * Integer paise, always. Rates are per million tokens, configured per model so a price
     * change is a config edit rather than a hunt through the codebase.
     */
    private function priceFor(string $model, int $inputTokens, int $outputTokens): int
    {
        /** @var array{input: float, output: float}|null $rate */
        $rate = config("ai.pricing.{$model}");

        if ($rate === null) {
            return 0;
        }

        return (int) ceil(
            $inputTokens / 1_000_000 * $rate['input']
            + $outputTokens / 1_000_000 * $rate['output']
        );
    }

    private function counterKey(User $user, string $feature, string $window): string
    {
        $bucket = $window === 'month'
            ? CarbonImmutable::now()->format('Y-m')
            : CarbonImmutable::now()->format('Y-m-d');

        return "ai:usage:{$user->id}:{$feature}:{$window}:{$bucket}";
    }

    private function windowStart(string $window): CarbonImmutable
    {
        return $window === 'month'
            ? CarbonImmutable::now()->startOfMonth()
            : CarbonImmutable::now()->startOfDay();
    }

    private function resetsAt(string $window): CarbonImmutable
    {
        return $window === 'month'
            ? CarbonImmutable::now()->startOfMonth()->addMonth()
            : CarbonImmutable::now()->startOfDay()->addDay();
    }
}
