<?php

declare(strict_types=1);

/**
 * Commerce configuration — and the place where the central product tension is resolved.
 *
 * Vol 1 Decision D2 says the core stays free forever and the business is ad-funded,
 * because traffic and habit are the moat and a paywall kills both. The brief for this
 * repository asks for real revenue from students.
 *
 * Both are satisfied by separating MACHINERY from POSTURE:
 *
 *   - the machinery is complete: plans, subscriptions, entitlements, credits, coupons,
 *     invoices, refunds, referrals. Money can be taken today.
 *   - the posture is `gates_open => true`. Nothing is actually withheld.
 *
 * Closing a gate is then a product decision someone makes on a date, for a reason, with a
 * metric in mind — never an accident of how a feature was written.
 *
 * NEVER_GATED below is the list that stays free at any revenue target. Those five are the
 * SEO engine and the habit loop. Gating them would trade a compounding asset for one
 * quarter of revenue, which is the single worst trade available in this business.
 */
return [

    /**
     * Master switch. While true, `FeatureGate` grants every capability regardless of
     * entitlement, and paid features remain fully usable. Purchases still work, subscriptions
     * still activate, entitlements are still recorded — so the day this flips, the data is
     * already there and nothing needs backfilling.
     */
    'gates_open' => (bool) env('COMMERCE_GATES_OPEN', true),

    /**
     * Free at the core, forever, regardless of `gates_open`.
     * FeatureGate throws if code ever asks it to gate one of these.
     */
    'never_gated' => [
        'notification_feed',
        'eligibility_check',
        'exam_hub_pages',
        'daily_quiz',
        'official_material',
    ],

    /**
     * Cost caps, not revenue gates.
     *
     * `gates_open` is a decision about what we WITHHOLD in order to sell it. These keys
     * withhold nothing — they raise a per-user limit that exists to keep the AI bill inside
     * the budget in `ai.cost`. Letting them follow the open posture hands every signed-up
     * user the premium allowance (200 notes a month, 200 questions a day, 60 answer
     * evaluations) against a budget of 40 paise per active user per month.
     *
     * That is not generosity, it is an accident of how the posture was written — exactly
     * what FeatureGate exists to prevent. So these stay entitlement-checked whether gates
     * are open or closed. Nobody loses a feature: the free caps are sized for genuine study.
     */
    'cost_capped' => [
        'ai_higher_caps',
    ],

    /**
     * Entitlement keys. Feature code NEVER asks "is this user premium" — it asks
     * "does this user hold this key". That indirection is what lets a plan change, a coupon,
     * a campus deal, a refund and a manual grant all flow through one code path.
     */
    'entitlements' => [
        'ads_free' => 'No display advertising',
        'test_series_full' => 'Every mock in every series, not just the free sample',
        'test_analytics_deep' => 'Percentile, rank, topic strength, comparison to top 10%',
        'ai_answer_eval' => 'Descriptive answer evaluation for mains',
        'ai_mock_interview' => 'Mock interview practice',
        'ai_higher_caps' => 'Raised daily and monthly AI limits',
        'material_bulk_download' => 'Bulk PDF download',
        'doubt_priority' => 'Doubts surfaced to verified answerers first',
        'offline_full' => 'Full offline library sync',
    ],

    'currency' => 'INR',

    /**
     * Gateway. Razorpay because UPI is how this audience actually pays — card penetration
     * in the target segment is low and UPI has no per-transaction floor that makes a
     * Rs 199 product uneconomic.
     *
     * Accessed only through the PaymentGateway interface so it stays swappable.
     */
    'gateway' => [
        'driver' => env('PAYMENT_GATEWAY', 'razorpay'),
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

        // UPI autopay mandates are opt-in, never pre-ticked. A surprise renewal on a
        // Rs 199 product buys one refund request and loses one user permanently.
        'auto_renew_default' => false,
    ],

    /**
     * GST. Education services attract varying treatment and the correct rate is a question
     * for the accountant, not for this file — so the rate is stored per invoice and this is
     * only the default applied at issue time.
     */
    'gst' => [
        'enabled' => (bool) env('GST_ENABLED', false),
        'default_rate' => (float) env('GST_RATE', 18.0),
        'home_state' => env('GST_HOME_STATE', 'TS'),
        'gstin' => env('COMPANY_GSTIN'),
        'invoice_prefix' => env('INVOICE_PREFIX', 'SDH'),
    ],

    /**
     * Plan catalogue seed. Deliberately cheap — Vol 1 anchors premium at Rs 199/year
     * because this audience is price-sensitive and the model is volume, not margin.
     * Prices are integer paise. Rs 199.00 is 19900.
     */
    'plans' => [
        'free' => [
            'price_paise' => 0,
            'period' => 'lifetime',
            'entitlements' => [],
            'ai_credits' => 0,
        ],
        'premium' => [
            'price_paise' => 19_900,
            'period' => 'yearly',
            'entitlements' => [
                'ads_free', 'test_series_full', 'test_analytics_deep',
                'material_bulk_download', 'doubt_priority', 'offline_full',
                'ai_higher_caps',
            ],
            'ai_credits' => 500,
        ],
        'group1_mains' => [
            // Answer evaluation costs roughly Rs 0.93 a use and mock interview more.
            // These are Group 1 features, and Group 1 aspirants are the cohort that will pay.
            'price_paise' => 99_900,
            'period' => 'yearly',
            'entitlements' => [
                'ads_free', 'test_series_full', 'test_analytics_deep',
                'ai_answer_eval', 'ai_mock_interview', 'ai_higher_caps',
                'material_bulk_download', 'doubt_priority', 'offline_full',
            ],
            'ai_credits' => 3_000,
        ],
    ],

    /**
     * Referrals. Reward is entitlement or credits, never cash — cash rewards invite
     * fake-account fraud, and this market has an active cottage industry in exactly that.
     * "Qualified" means the referred person actually used the product, not merely signed up.
     */
    'referral' => [
        'enabled' => true,
        'qualify_after_days' => 7,
        'qualify_requires_quiz_attempts' => 3,
        'referrer_reward' => ['type' => 'credits', 'value' => 100],
        'referred_reward' => ['type' => 'credits', 'value' => 50],
    ],

    'refund' => [
        'window_days' => 7,
        'auto_approve_under_paise' => 20_000,
    ],
];
