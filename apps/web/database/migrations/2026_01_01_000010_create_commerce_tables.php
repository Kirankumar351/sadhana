<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce — subscriptions, one-off purchases, entitlements, credits, referrals.
 *
 * THIS LAYER EXISTS IN NONE OF THE SOURCE DOCUMENTS. It was added deliberately, and the
 * tension with Decision D2 ("the core stays free forever, ad-funded") is resolved like this:
 *
 *   The machinery is built now. The gates default to OPEN.
 *
 * Every paid capability is an entitlement key. Whether that key is required is a config
 * flag (config/commerce.php), not a hardcoded check. So the platform can take money from
 * day one without any feature actually being withheld, and each gate is closed as a
 * deliberate product decision with a date and a reason — never as a side effect of code.
 *
 * What must never be gated, at any revenue target:
 *   notification feed, eligibility check, exam hub pages, daily quiz, official material.
 * Those five are the SEO engine and the habit loop. They are the moat. Gating them to
 * raise ARPU would trade a compounding asset for a quarter of revenue.
 *
 * MONEY IS ALWAYS INTEGER PAISE. Never a float, never a decimal rupee column that someone
 * will eventually round. Rs 199.00 is 19900.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * A plan is a sellable bundle of entitlements. Price is per (plan, billing period).
         * Deliberately cheap: Vol 1 sets Rs 199/year as the anchor, because this audience is
         * price-sensitive and volume is the business, not margin.
         */
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();          // free | premium | premium_plus | group1_mains
            $t->json('name');
            $t->json('description')->nullable();
            $t->json('entitlements');                  // ["ads_free","test_series_full","ai_eval", ...]

            $t->unsignedInteger('price_paise')->default(0);
            $t->enum('period', ['lifetime', 'monthly', 'quarterly', 'yearly'])->default('yearly');
            $t->unsignedSmallInteger('trial_days')->default(0);

            // AI credits granted per period. Caps are what keep the free product viable:
            // one uncapped user can cost what a thousand capped ones do.
            $t->unsignedInteger('ai_credits_per_period')->default(0);

            $t->boolean('is_active')->default(true);
            $t->boolean('is_public')->default(true);   // false = internal, campus, or grandfathered
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['is_active', 'is_public'], 'idx_sellable');
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained();

            $t->enum('status', [
                'trialing', 'active', 'past_due', 'cancelled', 'expired', 'paused',
            ])->default('active');

            $t->timestamp('started_at');
            $t->timestamp('current_period_start')->nullable();
            $t->timestamp('current_period_end')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancellation_reason', 300)->nullable();
            $t->boolean('auto_renew')->default(false);   // UPI mandates are opt-in, never default-on

            $t->string('gateway', 40)->nullable();       // razorpay
            $t->string('gateway_subscription_id', 120)->nullable();

            $t->timestamps();

            $t->index(['user_id', 'status'], 'idx_user_status');
            $t->index(['status', 'current_period_end'], 'idx_renewals');
        });

        /**
         * An order is intent to buy. A payment is money moving. They are separate because
         * a single order can have a failed attempt then a successful one, and reconciliation
         * against the gateway needs both histories.
         */
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->string('number', 40)->unique();          // human-quotable: SDH-2026-000123
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('item_type', 60);                 // plan | test_series | ai_credits
            $t->unsignedBigInteger('item_id')->nullable();
            $t->json('item_snapshot');                   // price and terms frozen at purchase time

            $t->unsignedInteger('subtotal_paise');
            $t->unsignedInteger('discount_paise')->default(0);
            $t->unsignedInteger('tax_paise')->default(0);
            $t->unsignedInteger('total_paise');
            $t->char('currency', 3)->default('INR');

            $t->foreignId('coupon_id')->nullable();
            $t->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'refunded'])->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'status'], 'idx_user_orders');
            $t->index(['status', 'created_at'], 'idx_status_time');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('gateway', 40)->default('razorpay');
            $t->string('gateway_payment_id', 120)->nullable();
            $t->string('gateway_order_id', 120)->nullable();
            $t->string('gateway_signature', 255)->nullable();

            $t->enum('method', ['upi', 'card', 'netbanking', 'wallet', 'emi', 'other'])->nullable();
            $t->unsignedInteger('amount_paise');
            $t->char('currency', 3)->default('INR');

            $t->enum('status', ['created', 'authorized', 'captured', 'failed', 'refunded'])
                ->default('created');
            $t->string('failure_reason', 300)->nullable();

            // Raw gateway payload, kept for dispute resolution. Card numbers never appear
            // here — the gateway handles PCI scope and we never see a PAN.
            $t->json('gateway_payload')->nullable();

            $t->timestamp('captured_at')->nullable();
            $t->timestamps();

            $t->unique(['gateway', 'gateway_payment_id'], 'uk_gateway_payment');
            $t->index(['order_id', 'status'], 'idx_order_status');
        });

        /**
         * Webhook inbox. Gateways retry, duplicate and arrive out of order, so every event is
         * stored before it is acted on and deduplicated by the gateway's own event id.
         * Processing a payment webhook twice would grant two subscriptions for one payment.
         */
        Schema::create('payment_webhooks', function (Blueprint $t) {
            $t->id();
            $t->string('gateway', 40);
            $t->string('event_id', 160);
            $t->string('event_type', 80);
            $t->json('payload');
            $t->boolean('signature_valid')->default(false);
            $t->enum('status', ['received', 'processed', 'failed', 'ignored'])->default('received');
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();

            $t->unique(['gateway', 'event_id'], 'uk_event');
            $t->index(['status', 'created_at'], 'idx_pending');
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount_paise');
            $t->string('reason', 400);
            $t->string('gateway_refund_id', 120)->nullable();
            $t->enum('status', ['requested', 'processing', 'completed', 'failed'])->default('requested');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_refund_queue');
        });

        /**
         * Entitlements are the ONLY thing feature code checks. Not "is the user premium" —
         * always "does the user hold this key". That indirection is what lets a plan change,
         * a coupon grant, a campus deal or a refund all flow through one code path.
         */
        Schema::create('entitlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('key', 80);                   // ads_free | test_series_full | ai_answer_eval | ...
            $t->string('source_type', 40);           // subscription | order | coupon | manual | referral
            $t->unsignedBigInteger('source_id')->nullable();
            $t->timestamp('granted_at');
            $t->timestamp('expires_at')->nullable(); // null = does not expire
            $t->boolean('is_revoked')->default(false);
            $t->string('revoke_reason', 300)->nullable();
            $t->timestamps();

            $t->index(['user_id', 'key', 'expires_at'], 'idx_lookup');
            $t->index('expires_at', 'idx_entitlement_expiry');
        });

        /**
         * AI credits as a ledger, not a balance column. Every grant and spend is a row, and
         * the balance is their sum. A mutable balance column loses the ability to answer
         * "where did my credits go", which is the only question anyone ever asks about credits.
         */
        Schema::create('credit_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->integer('delta');                    // positive = granted, negative = spent
            $t->string('reason', 80);                // plan_grant | purchase | ai_answer_eval | expiry | adjustment
            $t->string('reference_type', 60)->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->integer('balance_after');            // denormalised for fast reads and audit
            $t->timestamps();

            $t->index(['user_id', 'created_at'], 'idx_user_ledger');
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->json('description')->nullable();
            $t->enum('type', ['percent', 'fixed', 'entitlement'])->default('percent');
            $t->unsignedSmallInteger('percent_off')->nullable();
            $t->unsignedInteger('amount_off_paise')->nullable();
            $t->json('grants_entitlements')->nullable();

            $t->json('applicable_plans')->nullable();
            $t->unsignedInteger('max_redemptions')->nullable();
            $t->unsignedInteger('redemption_count')->default(0);
            $t->unsignedSmallInteger('per_user_limit')->default(1);

            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['is_active', 'ends_at'], 'idx_live');
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('discount_paise')->default(0);
            $t->timestamps();

            $t->index(['coupon_id', 'user_id'], 'idx_per_user');
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });

        /**
         * GST invoices. Education services attract varying treatment and the correct rate is
         * a question for the accountant, not the schema — so the rate is stored per invoice
         * rather than assumed in code, and `place_of_supply` drives CGST/SGST versus IGST.
         */
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 40)->unique();        // sequential per financial year, gapless
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('billing_name', 160);
            $t->string('billing_email', 190)->nullable();
            $t->string('billing_phone', 15)->nullable();
            $t->string('gstin', 20)->nullable();       // set when a coaching institute buys
            $t->char('place_of_supply', 2)->nullable();

            $t->unsignedInteger('taxable_paise');
            $t->decimal('gst_rate', 5, 2)->default(0);
            $t->unsignedInteger('cgst_paise')->default(0);
            $t->unsignedInteger('sgst_paise')->default(0);
            $t->unsignedInteger('igst_paise')->default(0);
            $t->unsignedInteger('total_paise');

            $t->string('pdf_path', 500)->nullable();
            $t->timestamp('issued_at');
            $t->timestamps();

            $t->index(['user_id', 'issued_at'], 'idx_user_invoices');
        });

        /**
         * Referrals. Vol 1 ch.9.1 channel 4: one student per degree college, given free
         * premium and a code, whose job is to post in their college groups.
         * Reward is an entitlement or credits — never cash, which invites fraud.
         */
        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('code', 40);
            $t->enum('status', ['pending', 'qualified', 'rewarded', 'rejected'])->default('pending');

            // Qualified means the referred user actually used the product, not merely signed
            // up. Rewarding a signup rewards fake accounts.
            $t->timestamp('qualified_at')->nullable();
            $t->string('reward_type', 40)->nullable();
            $t->integer('reward_value')->nullable();
            $t->timestamp('rewarded_at')->nullable();
            $t->timestamps();

            $t->index('code', 'idx_code');
            $t->index(['referrer_id', 'status'], 'idx_referrer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('invoices');

        Schema::table('orders', function (Blueprint $t) {
            $t->dropForeign(['coupon_id']);
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
