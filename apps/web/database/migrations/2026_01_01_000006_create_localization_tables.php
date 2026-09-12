<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localisation — glossary and the human translation review queue.
 *
 * THE GLOSSARY IS THE FIRST THING TO BUILD (Integration Map, corrected build order).
 * It has no dependencies, it is half a day of work, and ten downstream AI features
 * silently degrade without it. Every AI prompt injects it.
 *
 * Getting these words right is not a polish item — it is the SEO thesis. "Notification"
 * must stay నోటిఫికేషన్ and must not become ప్రకటన, because నోటిఫికేషన్ is what people
 * actually type into Google. A semantically correct translation that nobody searches for
 * ranks for nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary', function (Blueprint $t) {
            $t->id();
            $t->string('term_en', 160);
            $t->string('term_te', 160)->nullable();
            $t->string('term_hi', 160)->nullable();
            $t->string('term_ta', 160)->nullable();

            /**
             * translate         — render the meaning
             * transliterate     — keep the sound, change the script (Constable -> కానిస్టేబుల్)
             * do_not_translate  — leave in English entirely (SSC, RRB, IBPS)
             * latin_only        — numerals and currency stay Latin (2026, Rs 28,940)
             */
            $t->enum('rule', ['translate', 'transliterate', 'do_not_translate', 'latin_only'])
                ->default('transliterate');

            $t->string('category', 60)->nullable();   // exam_name | post_name | process | legal
            $t->text('note')->nullable();
            $t->timestamps();

            $t->unique('term_en', 'uk_term_en');
            $t->index('category', 'idx_category');
        });

        /**
         * AI may draft a translation. A human must approve anything a student will act on.
         *
         * `is_critical` is the load-bearing column: dates, fees and eligibility criteria can
         * never be auto-published in any language, no matter how good the model gets.
         */
        Schema::create('translation_queue', function (Blueprint $t) {
            $t->id();
            $t->string('model_type', 60);
            $t->unsignedBigInteger('model_id');
            $t->string('field', 60);
            $t->char('source_locale', 5);
            $t->char('target_locale', 5);
            $t->text('source_text');
            $t->text('translated_text')->nullable();
            $t->enum('status', ['pending', 'translated', 'approved', 'rejected'])->default('pending');
            $t->boolean('is_critical')->default(false);
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'is_critical'], 'idx_transq_status');
            $t->index(['model_type', 'model_id'], 'idx_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_queue');
        Schema::dropIfExists('glossary');
    }
};
