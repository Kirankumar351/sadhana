<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governance — audit, roles, DPDP rights, feature flags.
 *
 * Vol 1 ch.11.3 and Vol 2 ch.15.2 both say this is built in v1 and never retrofitted.
 * That is not compliance theatre: we hold dates of birth, categories, qualifications and
 * phone numbers for lakhs of people, and the DPDP Act 2023 gives each of them enforceable
 * rights over it.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Every admin action that touches user data or publishes content, with the actor's
         * name against it. The admin portal tells staff this on the sign-in screen, which is
         * the point: the deterrent only works if people know it exists.
         */
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80);              // published | approved | accessed_user_data | edited_prompt
            $t->string('auditable_type', 60)->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('reason', 400)->nullable(); // required when reading another user's data
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->timestamps();

            $t->index(['auditable_type', 'auditable_id'], 'idx_auditable');
            $t->index(['user_id', 'created_at'], 'idx_actor');
            $t->index(['action', 'created_at'], 'idx_action');
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();       // owner | content_lead | content_editor | moderator | translator | ad_sales
            $t->string('name', 80);
            $t->json('permissions');
            $t->timestamps();
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->primary(['role_id', 'user_id']);
        });

        /**
         * DPDP rights as a worked queue, not an email address.
         *
         * Integration Map gap 12: the export must include AI conversations, generated study
         * plans, flashcard decks and answer evaluations. All of it is personal data the user
         * is entitled to, and all of it must also go in the deletion routine.
         */
        Schema::create('data_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['export', 'deletion', 'correction']);
            $t->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');
            $t->string('export_path', 500)->nullable();
            $t->timestamp('export_expires_at')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_queue');
        });

        /**
         * Feature flags — including every commerce gate.
         *
         * Decision D12: paid gates default to OPEN. Closing one is a deliberate product
         * decision recorded here with a date and a reason, never a side effect of a deploy.
         */
        Schema::create('feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->boolean('is_enabled')->default(false);
            $t->unsignedTinyInteger('rollout_percent')->default(100);
            $t->json('constraints')->nullable();   // locale, state, plan, staff_only
            $t->string('description', 400)->nullable();
            $t->string('change_reason', 400)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        /**
         * Product analytics events. Deliberately thin and not personally identifying beyond
         * user_id, because Vol 1 ch.10.1 is explicit that the North Star is "weekly active
         * aspirants who completed a meaningful action" — not pageviews, which can be bought
         * and which lie.
         */
        Schema::create('analytics_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 80);
            $t->char('locale', 5)->nullable();
            $t->json('properties')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();

            $t->index(['name', 'occurred_at'], 'idx_name_time');
            $t->index(['user_id', 'occurred_at'], 'idx_user_time');
        });

        Schema::create('analytics_daily', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->string('metric', 80);
            $t->string('dimension', 80)->nullable();
            $t->decimal('value', 14, 2);
            $t->timestamps();

            $t->unique(['date', 'metric', 'dimension'], 'uk_metric');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('audit_logs');
    }
};
