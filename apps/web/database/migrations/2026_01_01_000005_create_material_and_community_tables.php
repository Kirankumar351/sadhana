<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Material library and doubt community.
 *
 * COPYRIGHT IS THE EXISTENTIAL RISK IN THIS MODULE. Vol 1 ch.11.1 and Decision D6:
 * we host only government-published documents, content we wrote, and user notes the
 * uploader personally authored and warrants as their own. Never a scanned coaching book.
 * `copyright_confirmed` records that warranty with a timestamp and IP; every upload is
 * human-reviewed before publication, with no exception at any volume.
 *
 * When material is removed after a complaint, its `ai_chunks` must go with it. A takedown
 * that leaves the corpus intact is incomplete in exactly the way that matters legally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('slug', 200)->unique();
            $t->json('title');
            $t->json('description')->nullable();
            $t->string('subject', 80)->nullable();
            $t->string('topic', 120)->nullable();
            $t->char('locale', 5);                     // the material's own language

            $t->string('file_path', 500)->nullable();  // R2 object key
            $t->unsignedInteger('file_size_kb')->nullable();
            $t->enum('file_type', ['pdf', 'html', 'image'])->default('pdf');

            // A 40 MB scan is unusable on a 2 GB phone. Every approved PDF gets a
            // mobile-readable HTML twin, which is also what Google can index.
            $t->longText('html_content')->nullable();

            // Integration Map gap 11: 'ai_assisted' plus a named editor shown publicly.
            // Readers deserve to know, and naming the editor creates accountability.
            $t->enum('source_type', ['official', 'original', 'user_notes', 'ai_assisted']);
            $t->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('source_url', 700)->nullable();

            $t->boolean('copyright_confirmed')->default(false);
            $t->string('copyright_ip', 45)->nullable();
            $t->timestamp('copyright_confirmed_at')->nullable();

            $t->enum('status', ['pending_review', 'published', 'rejected', 'taken_down'])
                ->default('pending_review');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason', 400)->nullable();

            $t->unsignedInteger('download_count')->default(0);
            $t->timestamps();
            $t->softDeletes();

            $t->index(['status', 'exam_id'], 'idx_status_exam');
            $t->index('download_count', 'idx_downloads');
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->string('slug', 250)->unique();
            $t->string('title', 300);
            $t->longText('body');
            $t->char('source_locale', 5);
            $t->string('subject', 80)->nullable();

            // Typing Telugu on a phone is painful, so a doubt can be a photo or a voice note.
            $t->string('image_path', 500)->nullable();
            $t->string('audio_path', 500)->nullable();

            $t->integer('upvotes')->default(0);
            $t->unsignedInteger('answer_count')->default(0);
            $t->unsignedBigInteger('best_answer_id')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->enum('status', ['published', 'flagged', 'removed'])->default('published');
            $t->timestamps();
            $t->softDeletes();

            $t->index(['exam_id', 'created_at'], 'idx_exam_recent');
            $t->index(['answer_count', 'created_at'], 'idx_unanswered');
        });

        // Full-text search over doubts, used by search-before-you-ask. MySQL only —
        // SQLite would need FTS5 virtual tables, which is not worth carrying for a test
        // suite that never exercises relevance ranking. Production search is Meilisearch;
        // this index is the fallback that keeps the feature working if Meilisearch is down.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE posts ADD FULLTEXT ft_search (title, body)');
        }

        Schema::create('answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->longText('body');
            $t->char('source_locale', 5);
            $t->string('image_path', 500)->nullable();
            $t->integer('upvotes')->default(0);
            $t->boolean('is_best')->default(false);

            /**
             * Integration Map gap 5. The doubt solver is the only AI feature that writes
             * straight into a user-facing table, so the flag is not cosmetic:
             *   - sort is (is_ai ASC, is_best DESC, upvotes DESC) — AI always below humans
             *   - AI answers earn no reputation
             *   - AI answers can never be marked best
             * A verified selected candidate always outranks the machine.
             */
            $t->boolean('is_ai')->default(false);
            $t->unsignedBigInteger('ai_request_id')->nullable();

            $t->enum('status', ['published', 'flagged', 'removed'])->default('published');
            $t->timestamps();
            $t->softDeletes();

            $t->index(['post_id', 'is_ai', 'upvotes'], 'idx_post_ranking');
        });

        Schema::create('votes', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('votable_type', 60);
            $t->unsignedBigInteger('votable_id');
            $t->tinyInteger('value');          // +1 / -1
            $t->timestamps();

            $t->primary(['user_id', 'votable_type', 'votable_id']);
            $t->index(['votable_type', 'votable_id'], 'idx_votable');
        });

        /**
         * Layer 3 of the localisation strategy: user-generated content is translated
         * lazily, on demand, and cached. Ninety percent of community posts are never read
         * in another language — pre-translating them all would burn budget for nothing.
         * Always labelled in the UI as machine translated.
         */
        Schema::create('content_translations', function (Blueprint $t) {
            $t->id();
            $t->string('translatable_type', 60);
            $t->unsignedBigInteger('translatable_id');
            $t->char('locale', 5);
            $t->longText('body');
            $t->enum('engine', ['ai', 'human'])->default('ai');
            $t->timestamps();

            $t->unique(['translatable_type', 'translatable_id', 'locale'], 'uk_translation');
        });

        /**
         * Moderation ladder (Vol 2 ch.10.3). Auto-hide at 3+ flags from users with 300+
         * reputation; a moderator reviews within 4 hours.
         *
         * Zero tolerance, permanent ban: exam paper leaks, impersonation of officials,
         * selling pirated material, and phone numbers posted for "guaranteed job" scams.
         * The last one is common in this market and does real financial harm.
         */
        Schema::create('moderation_flags', function (Blueprint $t) {
            $t->id();
            $t->string('flaggable_type', 60);
            $t->unsignedBigInteger('flaggable_id');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 60);
            $t->text('note')->nullable();
            $t->enum('status', ['open', 'dismissed', 'actioned'])->default('open');
            $t->enum('action_taken', ['none', 'edited', 'removed', 'warned', 'suspended', 'banned'])
                ->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_modflag_queue');
            $t->index(['flaggable_type', 'flaggable_id'], 'idx_flaggable');
        });

        /**
         * Study circles: auto-created per exam per language, capped at 200 members, then a
         * new circle spawns. Peer accountability is the mechanic — "12 of your circle
         * finished today's quiz" does more for retention than any notification we write.
         */
        Schema::create('study_circles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->char('locale', 5);
            $t->string('name', 160);
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->unsignedSmallInteger('member_count')->default(0);
            $t->unsignedSmallInteger('member_cap')->default(200);
            $t->boolean('is_open')->default(true);
            $t->timestamps();

            $t->index(['exam_id', 'locale', 'is_open'], 'idx_joinable');
        });

        Schema::create('circle_members', function (Blueprint $t) {
            $t->foreignId('study_circle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['member', 'moderator'])->default('member');
            $t->timestamps();

            $t->primary(['study_circle_id', 'user_id']);
            $t->index('user_id', 'idx_user_circles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('study_circles');
        Schema::dropIfExists('moderation_flags');
        Schema::dropIfExists('content_translations');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('materials');
    }
};
