<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI layer.
 *
 * SCHEMA CORRECTION, stated plainly. The Integration Map specifies
 * `embedding VECTOR(1536)` with a `VECTOR INDEX` on `ai_chunks`. MySQL 8 has no vector
 * type and no vector index — those arrived in MySQL 9. Rather than force a database
 * migration the rest of the stack does not need, vectors live in Qdrant and `ai_chunks`
 * stays in MySQL as the owner of record for chunk text and provenance. The two are joined
 * by `ai_chunks.id`, which is the Qdrant point id. `embedded_at` and `embedding_model`
 * make it possible to detect drift and re-embed after a model change.
 *
 * This preserves the rule that actually matters: the corpus is DERIVED, never authored.
 * Nobody edits a chunk. Observers rebuild chunks from owner tables, and deleting an owner
 * row deletes its chunks — otherwise a copyright takedown leaves the assistant still
 * quoting the removed material, which is the failure mode with legal consequences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chunks', function (Blueprint $t) {
            $t->id();
            $t->string('source_type', 60);        // exam | notification | material | question | answer | news_item
            $t->unsignedBigInteger('source_id');
            $t->char('source_locale', 5);
            $t->unsignedSmallInteger('chunk_index')->default(0);

            $t->text('content');
            $t->unsignedSmallInteger('token_count')->default(0);
            $t->string('checksum', 64);           // skip re-embedding when the text is unchanged

            $t->string('embedding_model', 60)->nullable();
            $t->timestamp('embedded_at')->nullable();
            $t->boolean('is_stale')->default(true);   // set by observers; the embed job clears it

            $t->json('metadata')->nullable();     // exam_id, subject, published_at — used as Qdrant filters

            $t->timestamps();

            $t->unique(['source_type', 'source_id', 'source_locale', 'chunk_index'], 'uk_chunk');
            $t->index(['source_type', 'source_id'], 'idx_source');
            $t->index('is_stale', 'idx_stale');
        });

        /**
         * Every AI interaction, for cost, audit and quality. This is also where the
         * per-user daily cap is counted from — Integration Map gap 4. The composite index
         * is not optional at this volume.
         */
        Schema::create('ai_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature', 60);            // ask | doubt_solver | explain | notes | plan | eval | cards | q_gen | news | material | extract | translate
            $t->string('model', 80);
            $t->string('prompt_version', 20)->nullable();

            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('cost_paise')->default(0);   // integer paise — never float money

            $t->decimal('confidence', 4, 3)->nullable();
            $t->json('sources_used')->nullable();
            $t->boolean('cache_hit')->default(false);
            $t->string('refused_reason', 60)->nullable();    // below_confidence | no_sources | eligibility_routed | guardrail
            $t->unsignedInteger('latency_ms')->nullable();

            $t->timestamps();

            $t->index(['feature', 'created_at'], 'idx_feature_time');
            $t->index(['user_id', 'feature', 'created_at'], 'idx_user_feature_time');
        });

        /**
         * Content-keyed, NOT user-keyed. "Explain this passage" is the same answer for
         * everyone, so one generation serves thousands. This single decision is why Explain
         * costs Rs 0.008 a call against Rs 0.074 for a doubt, and it is most of the reason
         * the AI layer fits inside Rs 0.40 per active user per month.
         */
        Schema::create('ai_cache', function (Blueprint $t) {
            $t->string('cache_key', 64)->primary();   // sha256(content + feature + locale + prompt_version)
            $t->longText('response');
            $t->char('locale', 5);
            $t->string('feature', 60);
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->index('expires_at', 'idx_aicache_expiry');
            $t->index(['feature', 'hits'], 'idx_popular');
        });

        /**
         * Anything a human must approve before it reaches a student. The approver's name
         * is written to the audit log. This table is the mechanical expression of the one
         * rule the whole AI layer rests on: AI drafts, a person decides.
         */
        Schema::create('ai_drafts', function (Blueprint $t) {
            $t->id();
            $t->string('type', 60);               // question | news_item | material | notification | translation
            $t->json('payload');
            $t->json('source_refs')->nullable();
            $t->decimal('model_confidence', 4, 3)->nullable();
            $t->string('flagged_reason', 300)->nullable();   // the model flagging its own uncertainty

            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason', 400)->nullable();  // feeds the monthly prompt review

            $t->unsignedBigInteger('promoted_id')->nullable(); // the row created on approval
            $t->timestamps();

            $t->index(['type', 'status', 'created_at'], 'idx_aidraft_queue');
        });

        /**
         * Reported bad output. Reports go to a person, not a model.
         * The resolution field is what makes this useful: most confirmed-wrong answers turn
         * out to be missing source material, which means the fix is content, not prompting.
         */
        Schema::create('ai_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ai_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 60);             // wrong_fact | bad_telugu | off_target | should_not_have_answered
            $t->text('note')->nullable();
            $t->enum('resolution', ['pending', 'prompt', 'corpus', 'no_issue'])->default('pending');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();

            $t->index(['resolution', 'created_at'], 'idx_open');
        });

        /**
         * Versioned prompts, editable in admin, every change logged.
         *
         * Prompt editing is Owner-only by design. A prompt is not content — it silently
         * changes the behaviour of every answer the product gives. Treating it as a text
         * field a content editor can adjust is how a product's voice drifts with nobody
         * noticing. Hard blocks are NOT in here: the eligibility route, the retrieval gate
         * and the output filters live in application code where no prompt edit can reach them.
         */
        Schema::create('ai_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60);                // grounded_answer | notification_extraction | question_generation | ...
            $t->unsignedSmallInteger('version');
            $t->enum('model_tier', ['small', 'large'])->default('large');
            $t->longText('system_prompt');
            $t->longText('user_template')->nullable();
            $t->json('parameters')->nullable();   // temperature, max_tokens
            $t->boolean('is_active')->default(false);
            $t->string('change_note', 500)->nullable();
            $t->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['key', 'version'], 'uk_key_version');
            $t->index(['key', 'is_active'], 'idx_aiprompt_active');
        });

        /**
         * Tunable thresholds, changeable without a deploy. Hard blocks are not here.
         * Raising the confidence floor makes the assistant say "I don't know" more often,
         * which is usually the right trade: a visible refusal costs less trust than a
         * fluent wrong answer.
         */
        Schema::create('ai_guardrails', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();      // confidence_floor | min_passages | free_questions_per_day | notes_per_month
            $t->string('value', 80);
            $t->string('description', 400)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_guardrails');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_reports');
        Schema::dropIfExists('ai_drafts');
        Schema::dropIfExists('ai_cache');
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_chunks');
    }
};
