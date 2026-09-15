<?php

declare(strict_types=1);

/**
 * AI layer configuration.
 *
 * THE ONE RULE: AI drafts, a person decides. Nothing an AI produced about a date, a fee,
 * an eligibility criterion or an answer key reaches a student without a human reading it
 * first — because the cost of being wrong there is somebody's year.
 *
 * Everything in `hard_blocks` below is enforced in APPLICATION CODE, not in prompt text.
 * A prompt instruction can be talked around by a determined user. The eligibility route,
 * the retrieval gate and the output filters cannot be.
 */
return [

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

    /**
     * Two tiers, used deliberately.
     *
     * The exam-relevance classifier reads 412 news articles every morning. Running that on
     * a large model would cost roughly 20x for no measurable gain. Routing, classification,
     * deduplication and filtering are small-model work; anything a student reads is large.
     */
    'models' => [
        'small' => env('AI_MODEL_SMALL', 'claude-haiku-4-5'),
        'large' => env('AI_MODEL_LARGE', 'claude-sonnet-5'),
        'embedding' => env('AI_MODEL_EMBEDDING', 'voyage-3'),
        'embedding_dimensions' => 1024,
    ],

    /**
     * Vector store.
     *
     * SCHEMA NOTE: the source documents specify a MySQL `VECTOR(1536)` column with a vector
     * index. MySQL 8 has neither — they arrived in MySQL 9. Vectors therefore live in
     * Qdrant, and `ai_chunks` remains the MySQL owner of record for chunk text and
     * provenance, joined by `ai_chunks.id` as the Qdrant point id.
     *
     * This preserves the rule that matters: the corpus is derived, never authored.
     */
    'vector' => [
        'driver' => env('AI_VECTOR_DRIVER', 'qdrant'),
        'host' => env('QDRANT_HOST', 'http://127.0.0.1:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'sadhana_chunks'),
        'distance' => 'Cosine',
    ],

    /**
     * Guardrails. These are the TUNABLE thresholds — see `hard_blocks` for the ones that
     * cannot be turned off at all. Live values are read from the `ai_guardrails` table so
     * they can be changed without a deploy; these are the defaults and the floor.
     */
    'guardrails' => [
        /**
         * Below this confidence the assistant says so and routes to the community.
         * Raising it makes the assistant admit ignorance more often, which is usually the
         * right trade: a visible refusal costs far less trust than a fluent wrong answer.
         */
        'confidence_floor' => 0.62,

        // No retrieved passages means no answer. Never free generation.
        'min_passages' => 2,

        'max_answer_tokens' => 1200,
    ],

    /**
     * Enforced in code. Not editable in admin. Not present in any prompt that a content
     * lead can edit. If one of these ever needs to change, it is a code review, not a
     * settings toggle.
     */
    'hard_blocks' => [
        'no_eligibility_outcomes' => true,  // EligibilityService decides, never a model
        'no_invented_dates' => true,  // a date absent from retrieved passages is stripped
        'no_cutoff_prediction' => true,  // we show five years of real data instead
        'no_selection_guarantee' => true,
        'no_answer_without_sources' => true,
        'no_copyrighted_reproduction' => true,  // enforced by restricting the corpus itself
    ],

    /**
     * Per-user caps. These are what keep the free product viable: without them a single
     * user can cost what a thousand do. 30 questions a day covers genuine study.
     *
     * Users holding the matching entitlement get the `premium` figure instead.
     */
    'caps' => [
        'free' => [
            'ask_questions_per_day' => 30,
            'notes_per_month' => 30,
            'flashcard_decks_per_month' => 10,
            'answer_evaluations_per_month' => 2,
            'mock_interviews_per_month' => 1,
        ],
        'premium' => [
            'ask_questions_per_day' => 200,
            'notes_per_month' => 200,
            'flashcard_decks_per_month' => 100,
            'answer_evaluations_per_month' => 60,
            'mock_interviews_per_month' => 20,
        ],
    ],

    /**
     * Cost discipline. The free product only works if this stays small.
     *
     * Target is Rs 0.40 per active user per month. The four things that keep it there:
     *   1. cache by CONTENT, not by user — one generation of "explain this passage" serves
     *      thousands, which is why Explain costs Rs 0.008 against Rs 0.074 for a doubt
     *   2. small model for routing and filtering
     *   3. hard per-user caps
     *   4. retrieval keeps prompts short — two good passages beat a whole syllabus in context
     */
    'cost' => [
        'budget_paise_per_active_user_per_month' => 40,
        'alert_at_percent_of_revenue' => 30,
        'monthly_ceiling_paise' => (int) env('AI_MONTHLY_CEILING_PAISE', 5_000_000),
    ],

    /**
     * Price per MILLION tokens, in paise, per model id.
     *
     * Kept here so a provider price change is a config edit rather than a hunt through the
     * codebase, and so `ai_requests.cost_paise` is computed from one place. A model absent
     * from this table records zero cost — which shows up immediately as a suspiciously free
     * feature on the usage dashboard rather than silently under-reporting for a month.
     *
     * Figures are illustrative and must be reconciled against the provider's live pricing
     * page before launch. Money is integer paise everywhere: never a float.
     */
    'pricing' => [
        'claude-haiku-4-5' => ['input' => 8_000.0,   'output' => 40_000.0],
        'claude-sonnet-5' => ['input' => 25_000.0,  'output' => 125_000.0],
        'claude-opus-5' => ['input' => 125_000.0, 'output' => 625_000.0],
        'voyage-3' => ['input' => 1_000.0,   'output' => 0.0],
    ],

    /**
     * Content-keyed cache TTLs, in seconds.
     * Explain is at 92% hit rate because a passage does not change; its TTL is long on purpose.
     */
    'cache_ttl' => [
        'explain' => 60 * 60 * 24 * 90,
        'ask' => 60 * 60 * 6,
        'notes' => 60 * 60 * 24 * 30,
        'flashcards' => 60 * 60 * 24 * 30,
        'translate' => 60 * 60 * 24 * 365,
    ],

    /**
     * Features, their tier, and whether they are enabled.
     *
     * `gate` names the entitlement required when the corresponding commerce gate is closed.
     * With gates open (the default posture) these are advisory only.
     */
    'features' => [
        'ask' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'doubt_solver' => ['tier' => 'large', 'enabled' => false, 'gate' => null],
        'explain' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'notes' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'flashcards' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'study_plan' => ['tier' => 'large', 'enabled' => false, 'gate' => null],
        'answer_eval' => ['tier' => 'large', 'enabled' => false, 'gate' => 'ai_answer_eval'],
        'mock_interview' => ['tier' => 'large', 'enabled' => false, 'gate' => 'ai_mock_interview'],
        'question_gen' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'news_pipeline' => ['tier' => 'small', 'enabled' => true,  'gate' => null],
        'material_gen' => ['tier' => 'large', 'enabled' => false, 'gate' => null],
        'extraction' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
        'translation' => ['tier' => 'large', 'enabled' => true,  'gate' => null],
    ],

    /**
     * Retention on ai_requests. Cost and audit data is valuable for months, not years,
     * and it is also personal data under the DPDP Act — so it is pruned, and the prune is
     * why every foreign key pointing at it is nullOnDelete.
     */
    'request_retention_days' => 180,
];
