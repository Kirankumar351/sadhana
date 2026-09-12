<?php

declare(strict_types=1);

/**
 * Agent configuration.
 *
 * An AI feature answers a question and returns. An agent has a goal, chooses its own
 * tools, runs several steps and remembers between runs. The safety rule does not change
 * between them: AN AGENT DRAFTS, A HUMAN DECIDES.
 *
 * No agent in this catalogue may publish. The highest autonomy that exists is `act`, which
 * covers changing internal state — reindexing a chunk, retrying a scraper, tagging a sales
 * lead. Anything a student will read goes to `ai_drafts` and waits for a person.
 *
 * Every agent ships DISABLED. Turning one on is a deliberate operational decision.
 */
return [

    'enabled' => (bool) env('AGENTS_ENABLED', false),

    /**
     * Global ceilings. An agent loop with a broken exit condition is the single most
     * expensive failure mode in an AI product, so the limits are enforced by the runtime
     * and cannot be raised by an agent definition.
     */
    'limits' => [
        'max_steps_hard'            => 25,
        'max_cost_paise_per_run'    => 20_000,
        'max_runs_per_agent_per_day' => 500,
        'timeout_sec'               => 600,
        'daily_spend_ceiling_paise' => (int) env('AGENTS_DAILY_CEILING_PAISE', 200_000),
    ],

    /**
     * Tool registry.
     *
     * `writes_owner` is the safety-critical flag. An owner table holds an eligibility
     * criterion, a date, a fee or an answer key. A tool that writes one may only ever be
     * bound behind human approval — the runtime refuses the binding otherwise.
     */
    'tools' => [
        // --- read-only over the corpus and owner tables ---
        'search_corpus'      => ['writes_owner' => false, 'approval' => false],
        'read_notification'  => ['writes_owner' => false, 'approval' => false],
        'read_exam'          => ['writes_owner' => false, 'approval' => false],
        'read_syllabus'      => ['writes_owner' => false, 'approval' => false],
        'read_cutoffs'       => ['writes_owner' => false, 'approval' => false],
        'read_past_papers'   => ['writes_owner' => false, 'approval' => false],
        'read_user_performance' => ['writes_owner' => false, 'approval' => false],
        'fetch_url'          => ['writes_owner' => false, 'approval' => false],
        'check_eligibility'  => ['writes_owner' => false, 'approval' => false], // calls the deterministic engine

        // --- draft-only: writes to ai_drafts, never to an owner table ---
        'draft_question'     => ['writes_owner' => false, 'approval' => true],
        'draft_news_item'    => ['writes_owner' => false, 'approval' => true],
        'draft_material'     => ['writes_owner' => false, 'approval' => true],
        'draft_translation'  => ['writes_owner' => false, 'approval' => true],
        'draft_notification' => ['writes_owner' => false, 'approval' => true],

        // --- internal state, safe to act on ---
        'reindex_chunks'     => ['writes_owner' => false, 'approval' => false],
        'retry_scraper'      => ['writes_owner' => false, 'approval' => false],
        'tag_lead'           => ['writes_owner' => false, 'approval' => false],
        'write_memory'       => ['writes_owner' => false, 'approval' => false],
        'raise_alert'        => ['writes_owner' => false, 'approval' => false],

        // --- explicitly forbidden to every agent, listed so the absence is deliberate ---
        // publish_notification, set_eligibility, approve_question, send_push_broadcast,
        // issue_refund, change_prompt. These are human actions. There is no tool for them.
    ],

    /**
     * The catalogue. Definitions are seeded into `agent_definitions` and edited from the
     * admin panel thereafter — an agent is configuration, not a class, because the ones
     * worth having are discovered while operating the product, not while designing it.
     */
    'catalogue' => [

        // ---------- OPS: the content autopilot ----------
        'scraper_medic' => [
            'family'   => 'ops',
            'autonomy' => 'act',
            'schedule' => '*/30 * * * *',
            'tools'    => ['fetch_url', 'retry_scraper', 'raise_alert'],
            'goal'     => 'Watch every scrape source. When one fails three runs in a row, diagnose whether the site layout changed, the site is down, or we are being rate-limited, and raise an alert naming which. Government sites change layout without warning and a silent scraper on notification day costs us the ranking.',
        ],

        'news_curator' => [
            'family'   => 'ops',
            'autonomy' => 'propose',
            'schedule' => '0 4 * * *',
            'tools'    => ['fetch_url', 'read_past_papers', 'search_corpus', 'draft_news_item'],
            'goal'     => 'Scan overnight news, keep only what these exams actually ask, deduplicate across sources, and draft a Telugu summary with the exam angle and two draft questions. Require two independent sources for every factual claim. Hold anything single-sourced, and hold appointments and casualty figures regardless, because those get reported early and corrected later.',
        ],

        'question_smith' => [
            'family'   => 'ops',
            'autonomy' => 'propose',
            'tools'    => ['read_syllabus', 'read_past_papers', 'search_corpus', 'draft_question'],
            'goal'     => 'Generate bilingual MCQs for a topic with plausible distractors matched to the paper style. Flag your own uncertainty rather than guessing a key. Never produce a question whose answer is disputed between credible sources.',
        ],

        'seo_scout' => [
            'family'   => 'ops',
            'autonomy' => 'propose',
            'schedule' => '0 3 * * 1',
            'tools'    => ['read_exam', 'search_corpus', 'fetch_url', 'raise_alert'],
            'goal'     => 'Find Telugu exam queries where our page is absent or ranks below page one, and identify which of the eleven exam-hub sections is thin or missing on those pages. Report the gap; do not edit the page.',
        ],

        'corpus_keeper' => [
            'family'   => 'platform',
            'autonomy' => 'act',
            'schedule' => '0 2 * * *',
            'tools'    => ['search_corpus', 'reindex_chunks', 'raise_alert'],
            'goal'     => 'Keep the retrieval corpus honest. Re-embed stale chunks, remove chunks whose owner row is gone, and report topics where coverage is thin enough that generated notes would be poor.',
        ],

        // ---------- STUDENT: the tutor ----------
        'tutor' => [
            'family'   => 'student',
            'autonomy' => 'propose',
            'tools'    => [
                'search_corpus', 'read_syllabus', 'read_cutoffs', 'read_past_papers',
                'read_user_performance', 'check_eligibility', 'write_memory',
            ],
            'goal'     => 'Be one aspirant\'s tutor. Know their target exam and date, their weak subjects from quiz history, and what they have already covered. Answer only from indexed material. Never state an eligibility outcome — call the eligibility tool and report what it returns. Never predict a cutoff; show the real history instead. Be direct: this person is short of time and under pressure.',
        ],

        // ---------- BUSINESS ----------
        'ad_prospector' => [
            'family'   => 'business',
            'autonomy' => 'propose',
            'schedule' => '0 6 * * 1',
            'tools'    => ['fetch_url', 'tag_lead', 'raise_alert'],
            'goal'     => 'Find coaching institutes, study hostels and libraries in districts where we have concentrated readership, and draft an outreach note naming the district and exam audience they would be buying. District-level targeting is the thing nobody else can sell them.',
        ],

        'campaign_optimiser' => [
            'family'   => 'business',
            'autonomy' => 'propose',
            'schedule' => '0 7 * * *',
            'tools'    => ['raise_alert'],
            'goal'     => 'Watch live ad campaigns for under-delivery against their cap and for placements performing far below their district average. Propose a reallocation. Never pause a paying campaign without a person.',
        ],

        'retention_watch' => [
            'family'   => 'business',
            'autonomy' => 'propose',
            'schedule' => '0 8 * * *',
            'tools'    => ['read_user_performance', 'raise_alert'],
            'goal'     => 'Identify cohorts whose streak or quiz completion is falling, and subscribers approaching renewal with low recent usage. Propose a specific intervention. Do not send anything yourself.',
        ],

        'cost_sentinel' => [
            'family'   => 'platform',
            'autonomy' => 'act',
            'schedule' => '15 * * * *',
            'tools'    => ['raise_alert'],
            'goal'     => 'Track AI spend against the monthly ceiling and against revenue. Alert when any feature exceeds its expected cost per request, or when AI cost passes 30% of revenue. Cost discipline is what keeps the free product possible.',
        ],
    ],

    /**
     * Where the agent runtime executes.
     *
     * `php` runs the loop in a Laravel queue worker — simplest, and correct for the ops
     * agents that mostly call internal tools. `service` delegates to apps/ai, which is
     * where retrieval, embeddings and anything needing the Python ecosystem belong.
     */
    'runtime' => [
        'driver'       => env('AGENT_RUNTIME', 'php'),
        'service_url'  => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'service_token' => env('AI_SERVICE_TOKEN'),
        'queue'        => 'agents',
    ],

    /**
     * An agent that is not evaluated silently rots when a prompt changes.
     * `php artisan agents:eval` replays each golden set before a definition change ships.
     */
    'eval' => [
        'run_before_activation' => true,
        'min_pass_rate'         => 0.9,
    ],
];
