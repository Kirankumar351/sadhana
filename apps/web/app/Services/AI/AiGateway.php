<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\Exceptions\CapExceededException;
use App\Services\AI\Exceptions\GuardrailException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single entry point for every AI call in the product. Nothing else may talk to a
 * model API directly.
 *
 * WHY A GATEWAY. Cost caps, refusal logging, prompt versioning, content caching and output
 * filtering all live in one place. If any feature could call the API directly, every one of
 * those guarantees becomes optional — and the one that gets skipped will be the one that
 * mattered. This is enforced by an architecture test (tests/Architecture) that fails the
 * build if any class outside this namespace references the provider client.
 *
 * The pipeline, in order, and each step can end the request:
 *
 *   1. INTENT ROUTE   eligibility, dates, fees -> deterministic engine. The model is never
 *                     consulted about whether a person qualifies for anything.
 *   2. CAP CHECK      per-user daily and monthly limits, counted from ai_requests.
 *   3. CACHE          content-keyed, never user-keyed. One generation serves thousands.
 *   4. RETRIEVE       our own corpus only. No passages means no answer — the assistant
 *                     hands over to the community rather than generating freely.
 *   5. GENERATE       versioned prompt + retrieved passages + glossary.
 *   6. OUTPUT GUARD   strips invented dates, blocks guarantee language, requires sources.
 *   7. METER          writes ai_requests: tokens, cost, confidence, sources, refusals.
 */
final class AiGateway
{
    public function __construct(
        private readonly IntentRouter $router,
        private readonly Retriever $retriever,
        private readonly PromptRegistry $prompts,
        private readonly OutputGuard $guard,
        private readonly CostMeter $meter,
        private readonly ResponseCache $cache,
        private readonly ModelClient $client,
    ) {}

    /**
     * Answer a grounded question.
     *
     * @param  array<string, mixed>  $context  feature-specific scope, e.g. notification_id
     */
    public function ask(
        string $question,
        ?User $user,
        string $feature = 'ask',
        array $context = [],
        ?string $locale = null,
    ): AiResult {
        $locale ??= app()->getLocale();
        $started = microtime(true);

        try {
            // 1. Some questions must never reach a model.
            $route = $this->router->route($question, $context);

            if ($route->isDeterministic()) {
                $this->meter->recordRefusal($user, $feature, $route->reason);

                return AiResult::handedOff($route->handler, $route->reason);
            }

            // 2. Caps exist so one user cannot cost what a thousand do.
            $this->meter->assertWithinCaps($user, $feature);

            // 3. Content-keyed cache. The same passage explained once serves everyone.
            $cacheKey = $this->cache->keyFor($feature, $question, $locale, $context);

            if ($hit = $this->cache->get($cacheKey)) {
                $this->meter->recordCacheHit($user, $feature);

                return AiResult::fromCache($hit);
            }

            // 4. Retrieval gate. No corpus, no answer.
            $passages = $this->retriever->retrieve($question, $locale, $context);

            if (count($passages) < (int) config('ai.guardrails.min_passages')) {
                $this->meter->recordRefusal($user, $feature, 'no_sources');

                return AiResult::noSources();
            }

            // 5. Generate against a versioned prompt.
            $prompt = $this->prompts->resolve($this->promptKeyFor($feature), $locale);

            $response = $this->client->complete(
                prompt: $prompt,
                passages: $passages,
                question: $question,
                tier: (string) config("ai.features.{$feature}.tier", 'large'),
            );

            // 6. The prompt is the weakest of the three layers — a user can talk around
            //    instructions. These filters are code, and cannot be talked around.
            $guarded = $this->guard->inspect($response, $passages);

            if ($guarded->blocked) {
                $this->meter->recordRefusal($user, $feature, $guarded->reason ?? 'guardrail');

                return AiResult::blocked($guarded->reason ?? 'guardrail');
            }

            if ($guarded->confidence < (float) config('ai.guardrails.confidence_floor')) {
                $this->meter->recordRefusal($user, $feature, 'below_confidence');

                return AiResult::lowConfidence($guarded->confidence, $passages);
            }

            $result = AiResult::answered(
                text: $guarded->text,
                sources: $passages,
                confidence: $guarded->confidence,
                promptVersion: $prompt->version,
            );

            // 7. Meter before caching, so a request that fails to cache is still accounted for.
            $this->meter->record(
                user: $user,
                feature: $feature,
                model: $response->model,
                promptVersion: $prompt->version,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                confidence: $guarded->confidence,
                sources: $passages,
                latencyMs: (int) ((microtime(true) - $started) * 1000),
            );

            $this->cache->put($cacheKey, $result, $feature);

            return $result;
        } catch (CapExceededException $e) {
            return AiResult::capReached($e->resetsAt, $e->used, $e->limit);
        } catch (GuardrailException $e) {
            $this->meter->recordRefusal($user, $feature, $e->getMessage());

            return AiResult::blocked($e->getMessage());
        } catch (Throwable $e) {
            // An AI failure must never take down the page it sits on. Every AI surface in
            // the product degrades to "ask the community" rather than to an error.
            report($e);
            Log::warning('ai.gateway.failed', ['feature' => $feature, 'error' => $e->getMessage()]);

            return AiResult::unavailable();
        }
    }

    private function promptKeyFor(string $feature): string
    {
        return match ($feature) {
            'ask', 'doubt_solver' => 'grounded_answer',
            'explain' => 'explain_simpler',
            'notes' => 'notes_generator',
            'study_plan' => 'study_planner',
            'answer_eval' => 'answer_evaluation_rubric',
            'question_gen' => 'question_generation',
            'news_pipeline' => 'exam_relevance_classifier',
            'material_gen' => 'material_generator',
            'extraction' => 'notification_extraction',
            'translation' => 'telugu_translation',
            default => 'grounded_answer',
        };
    }
}
