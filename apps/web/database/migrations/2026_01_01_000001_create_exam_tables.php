<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exams — the SEO engine and the spine of the corpus.
 *
 * Every translatable field is a JSON column shaped {"en": "...", "te": "..."} and read
 * through spatie/laravel-translatable. Slugs are NEVER translated: one slug per entity
 * across every locale, or backlinks fragment and the ranking that the whole business
 * model rests on is split in half.
 *
 * Soft deletes are mandatory here. Deleting an exam page loses its search position
 * permanently, and a three-year-old ranking cannot be rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();          // 'state-psc', 'railway', 'banking'
            $t->json('name');
            $t->string('icon', 60)->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('exam_categories');
            $t->string('slug', 120)->unique();         // 'tgpsc-group-2'
            $t->json('name');
            $t->string('short_name', 60);
            $t->string('conducting_body', 120);
            $t->string('official_website', 255)->nullable();

            $t->json('description')->nullable();
            $t->json('eligibility_summary')->nullable();
            $t->json('exam_pattern')->nullable();      // structured, per locale
            $t->json('syllabus')->nullable();          // structured, per locale — feeds notes + question generation
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->json('faq')->nullable();               // rendered as schema.org FAQPage

            $t->char('state', 2)->nullable();          // null = all-India
            $t->boolean('is_active')->default(true);
            $t->boolean('has_interview')->default(false);   // gates the mock-interview feature
            $t->boolean('has_mains')->default(false);       // gates answer evaluation
            $t->unsignedBigInteger('view_count')->default(0);

            $t->timestamps();
            $t->softDeletes();

            $t->index(['is_active', 'state'], 'idx_active_state');
            $t->index('view_count', 'idx_views');
        });

        Schema::create('exam_cutoffs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->smallInteger('year');
            $t->string('category', 20);
            $t->decimal('cutoff_marks', 6, 2)->nullable();
            $t->decimal('total_marks', 6, 2)->nullable();
            $t->json('notes')->nullable();

            // Required, not optional. A cutoff without a verifiable source is a rumour,
            // and rumours about cutoffs spread further than anything else in this market.
            $t->string('source_url', 500)->nullable();

            $t->timestamps();

            $t->index(['exam_id', 'year'], 'idx_exam_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_cutoffs');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_categories');
    }
};
