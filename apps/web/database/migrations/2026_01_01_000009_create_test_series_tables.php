<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test series — full-length mocks with real analytics.
 *
 * The free tier is not a crippled demo: one full-length mock per exam is free forever,
 * plus the daily quiz. What is paid is the full series and the deep analytics. This keeps
 * Decision D2 intact — a student can prepare seriously without paying — while giving the
 * commercial layer something genuinely worth buying.
 *
 * Whether any given series is actually gated is decided by `is_free` plus the entitlement
 * check, and the default posture for the whole platform is OPEN (see config/commerce.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_series', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->string('slug', 160)->unique();
            $t->json('title');
            $t->json('description')->nullable();
            $t->unsignedSmallInteger('test_count')->default(0);
            $t->boolean('is_free')->default(false);
            $t->unsignedInteger('price_paise')->nullable();     // standalone purchase price
            $t->string('sku', 80)->nullable();                  // links to a plan/entitlement
            $t->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $t->timestamps();
            $t->softDeletes();

            $t->index(['exam_id', 'status'], 'idx_exam_status');
        });

        Schema::create('tests', function (Blueprint $t) {
            $t->id();
            // Named explicitly: 'series' is uncountable, so the inflector leaves
            // 'test_series' unchanged. Correct, but not obvious enough to depend on.
            $t->foreignId('test_series_id')->constrained('test_series')->cascadeOnDelete();
            $t->string('slug', 160)->unique();
            $t->json('title');
            $t->unsignedSmallInteger('sequence')->default(1);

            $t->unsignedSmallInteger('duration_min');
            $t->decimal('total_marks', 7, 2);
            $t->decimal('negative_marking', 4, 2)->default(0);   // e.g. 0.25 per wrong answer

            // The first mock in a series is free even in a paid series: people buy what they
            // have tried. Gating the sample is how you lose the sale and the trust together.
            $t->boolean('is_free_sample')->default(false);

            $t->timestamp('available_from')->nullable();
            $t->enum('status', ['draft', 'published'])->default('draft');
            $t->timestamps();

            $t->index(['test_series_id', 'sequence'], 'idx_sequence');
        });

        Schema::create('test_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->json('question_ids');
            $t->decimal('marks_per_question', 5, 2)->default(1);
            $t->unsignedSmallInteger('duration_min')->nullable();   // null = shared overall timer
            $t->timestamps();

            $t->index('test_id', 'idx_test');
        });

        // Deferred FK from the quiz migration: a quiz_attempt may belong to a full test.
        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->foreign('test_id')->references('id')->on('tests')->nullOnDelete();
        });

        /**
         * Post-test analytics, computed once at submission and stored.
         * Percentile and rank are relative to everyone who has taken the same test, so they
         * are recomputed on a schedule as more attempts arrive rather than read live.
         */
        Schema::create('test_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->decimal('score', 7, 2);
            $t->decimal('percentile', 5, 2)->nullable();
            $t->unsignedInteger('rank')->nullable();
            $t->unsignedInteger('total_attempts')->nullable();

            $t->json('section_breakdown')->nullable();
            $t->json('topic_strength')->nullable();
            $t->json('time_per_question')->nullable();
            $t->json('comparison_to_top')->nullable();   // versus the top 10%
            $t->json('weak_areas')->nullable();          // routes back into the free library

            $t->timestamps();

            $t->unique('quiz_attempt_id', 'uk_attempt');
            $t->index(['test_id', 'score'], 'idx_leaderboard');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');

        Schema::table('quiz_attempts', function (Blueprint $t) {
            $t->dropForeign(['test_id']);
        });

        Schema::dropIfExists('test_sections');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('test_series');
    }
};
