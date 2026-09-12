<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily quiz and streaks — the retention engine.
 *
 * Vol 1 ch.13.2 names D7 retention above 20% as the highest-risk assumption in the whole
 * business. This module is the mechanism that has to deliver it. If it does not work,
 * nothing built on top of it will.
 *
 * `questions.correct_index` is the single most dangerous column in the database. A fluent
 * question with a wrong key teaches thousands of people something false and they carry it
 * into the exam hall. Every key is human-verified before the row leaves draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->string('subject', 80)->nullable();
            $t->string('topic', 120)->nullable();
            $t->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $t->enum('question_type', ['mcq', 'multi', 'numeric'])->default('mcq');

            $t->json('question');         // {"en": "...", "te": "..."}
            $t->json('options');          // {"en": [...], "te": [...]}
            $t->tinyInteger('correct_index');
            $t->json('explanation')->nullable();

            $t->smallInteger('source_year')->nullable();      // previous-year tagging
            $t->boolean('is_current_affairs')->default(false);
            $t->foreignId('news_item_id')->nullable();        // FK added in the news migration

            // Provenance: who or what produced this, and who signed off on the key.
            $t->enum('origin', ['human', 'ai_generated', 'imported'])->default('human');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();

            $t->unsignedInteger('times_served')->default(0);
            $t->unsignedInteger('times_correct')->default(0);
            $t->boolean('is_disputed')->default(false);       // raised by users; pulled from rotation

            $t->timestamps();
            $t->softDeletes();

            $t->index(['subject', 'topic'], 'idx_subject_topic');
            $t->index(['is_current_affairs', 'difficulty', 'times_served'], 'idx_selection');
            $t->index('is_disputed', 'idx_disputed');
        });

        Schema::create('daily_quizzes', function (Blueprint $t) {
            $t->id();
            $t->date('quiz_date')->unique();
            $t->json('question_ids');
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('attempt_count')->default(0);
            $t->timestamps();

            $t->index('quiz_date', 'idx_date');
        });

        Schema::create('quiz_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Table named explicitly: the inflector turns 'daily_quiz' into 'daily_quizzes'
            // correctly, but relying on that is a silent dependency on pluralisation rules.
            $t->foreignId('daily_quiz_id')->nullable()->constrained('daily_quizzes')->nullOnDelete();
            $t->unsignedBigInteger('test_id')->nullable();    // FK added in the test-series migration

            $t->json('answers');          // [{"q":1,"a":2,"t":14}]  t = seconds on the question
            $t->decimal('score', 6, 2);
            $t->decimal('total_marks', 6, 2);
            $t->unsignedInteger('time_taken_sec')->nullable();
            $t->json('subject_breakdown')->nullable();        // rolled up at submit, drives the study plan
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'created_at'], 'idx_user_date');
            $t->index(['daily_quiz_id', 'score'], 'idx_quiz_score');
        });

        Schema::create('streaks', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('current_streak')->default(0);
            $t->unsignedInteger('longest_streak')->default(0);
            $t->date('last_active_date')->nullable();

            // One free miss a month. Users have exams, travel and family emergencies; a streak
            // that punishes real life gets abandoned, and once abandoned it never restarts.
            $t->unsignedTinyInteger('freezes_left')->default(1);
            $t->date('freeze_reset_at')->nullable();

            $t->timestamps();

            $t->index('current_streak', 'idx_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('daily_quizzes');
        Schema::dropIfExists('questions');
    }
};
