<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current affairs pipeline and the personal learning artefacts.
 *
 * Current affairs is the binding constraint on the daily quiz — it cannot be written
 * months ahead — and the daily quiz is the retention engine. That is why this moved
 * earlier in the corrected build order: it unblocks the metric the whole business rests on.
 *
 * The two-source rule is enforced in DATA, not in a checklist. A factual claim with fewer
 * than two rows in `news_item_sources` cannot be published. Appointments and casualty
 * figures are exactly the items that get reported early and corrected later, and a wrong
 * name in a quiz answer is remembered for months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('url', 700);
            $t->string('parser_class', 160)->nullable();
            $t->unsignedTinyInteger('trust_weight')->default(5);   // PIB and RBI outrank a daily paper
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });

        Schema::create('news_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('news_source_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source_name', 120);
            $t->string('source_url', 700);
            $t->timestamp('published_at')->nullable();
            $t->longText('raw_text')->nullable();

            $t->json('title')->nullable();           // translatable
            $t->json('summary')->nullable();
            $t->json('why_it_matters')->nullable();  // the exam angle — this is the actual value

            $t->decimal('relevance_score', 4, 3)->nullable();   // small-model classifier
            $t->string('dedupe_group', 64)->nullable();         // same story across sources
            $t->json('exam_tags')->nullable();
            $t->enum('probability', ['high', 'medium', 'low'])->nullable();

            $t->enum('status', [
                'scanned', 'candidate', 'drafted', 'verified', 'approved', 'rejected', 'published', 'held',
            ])->default('scanned');
            $t->string('hold_reason', 300)->nullable();

            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->date('digest_date')->nullable();     // which daily digest it appears in

            $t->timestamps();

            $t->index(['status', 'published_at'], 'idx_news_status_published');
            $t->index('dedupe_group', 'idx_dedupe');
            $t->index(['digest_date', 'probability'], 'idx_digest');
        });

        Schema::create('news_item_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('news_item_id')->constrained()->cascadeOnDelete();
            $t->string('source_url', 700);
            $t->string('source_name', 120);
            $t->string('claim', 400)->nullable();    // which fact this source corroborates
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();

            $t->index('news_item_id', 'idx_item');
        });

        // Deferred FK from the quiz migration: a question may originate in a news item.
        Schema::table('questions', function (Blueprint $t) {
            $t->foreign('news_item_id')->references('id')->on('news_items')->nullOnDelete();
        });

        /**
         * Spaced repetition. SM-2 style: ease, interval, due date.
         * Cards come from syllabus topics, current affairs, or — most valuable — the user's
         * own wrong answers, which is how the quiz feeds back into study.
         */
        Schema::create('flashcards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('deck', 160);
            $t->json('front');
            $t->json('back');
            $t->char('locale', 5);
            $t->enum('source_type', ['ai_topic', 'ai_news', 'wrong_answer', 'manual']);
            $t->string('source_ref', 120)->nullable();

            $t->decimal('ease', 3, 2)->default(2.50);
            $t->unsignedSmallInteger('interval_days')->default(0);
            $t->date('due_at')->nullable();
            $t->unsignedSmallInteger('review_count')->default(0);
            $t->unsignedSmallInteger('lapse_count')->default(0);

            $t->timestamps();

            $t->index(['user_id', 'due_at'], 'idx_flashcard_due');
            $t->index(['user_id', 'deck'], 'idx_deck');
        });

        Schema::create('study_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->date('target_date');
            $t->json('plan');                     // week-by-week allocation
            $t->json('weak_subjects')->nullable();
            $t->unsignedSmallInteger('based_on_attempts')->default(0);  // a plan built on 5 attempts is noise
            $t->timestamp('generated_at');
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'superseded_at'], 'idx_current_plan');
        });

        /**
         * Descriptive answer practice for Group 1 mains.
         * Feedback, not a mark. No model can predict what an examiner will award; what it
         * can do reliably is name the required points that were not written, and that is
         * what actually improves scores.
         */
        Schema::create('mains_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->string('paper', 60)->nullable();
            $t->string('subject', 80)->nullable();
            $t->json('question');
            $t->unsignedSmallInteger('marks');
            $t->unsignedSmallInteger('word_limit')->nullable();
            $t->string('directive', 40)->nullable();   // examine | discuss | critically analyse
            $t->json('rubric');                        // published, not hidden — the user can see it
            $t->json('model_answer')->nullable();
            $t->smallInteger('source_year')->nullable();
            $t->timestamps();

            $t->index(['exam_id', 'paper'], 'idx_exam_paper');
        });

        Schema::create('answer_evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('mains_question_id')->constrained()->cascadeOnDelete();
            $t->longText('answer_text')->nullable();
            $t->string('answer_image_path', 500)->nullable();   // photograph of handwriting
            $t->json('rubric');                                 // the rubric as applied, frozen
            $t->decimal('band', 5, 2)->nullable();              // indicative only, never "your mark"
            $t->unsignedSmallInteger('word_count')->nullable();
            $t->json('points_hit')->nullable();
            $t->json('points_missed')->nullable();
            $t->text('single_biggest_gain')->nullable();
            $t->unsignedBigInteger('ai_request_id')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'created_at'], 'idx_user_history');
        });

        Schema::create('interview_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->json('bio_data');                  // district, graduation subject, hobbies, optional
            $t->enum('language', ['te', 'en', 'mixed'])->default('te');
            $t->unsignedSmallInteger('duration_sec')->nullable();
            $t->unsignedTinyInteger('question_count')->default(0);
            $t->json('report')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'created_at'], 'idx_user_sessions');
        });

        Schema::create('interview_turns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('interview_session_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('turn_index');
            $t->unsignedTinyInteger('board_member')->default(1);
            $t->text('question');
            $t->text('answer')->nullable();
            $t->string('audio_path', 500)->nullable();
            $t->json('feedback')->nullable();
            $t->timestamps();

            $t->unique(['interview_session_id', 'turn_index'], 'uk_turn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_turns');
        Schema::dropIfExists('interview_sessions');
        Schema::dropIfExists('answer_evaluations');
        Schema::dropIfExists('mains_questions');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('flashcards');

        Schema::table('questions', function (Blueprint $t) {
            $t->dropForeign(['news_item_id']);
        });

        Schema::dropIfExists('news_item_sources');
        Schema::dropIfExists('news_items');
        Schema::dropIfExists('news_sources');
    }
};
