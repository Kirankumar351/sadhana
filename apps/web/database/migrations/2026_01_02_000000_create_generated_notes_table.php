<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes a student generated and kept.
 *
 * The generation itself is cached content-side in ai_cache and serves everyone who asks for
 * the same topic at the same depth — roughly three in four requests, because a syllabus has
 * a finite number of topics. THIS TABLE IS NOT THAT CACHE. It is the student's own library:
 * what they chose to save, in the order they saved it, deletable by them.
 *
 * Which is why the body is stored rather than referenced. A note the student saved must
 * keep saying what it said when they saved it, even after the prompt is reversioned or the
 * cache is purged — revising from notes that silently changed under you is worse than
 * having no notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_notes', function (Blueprint $t) {
            $t->id();
            // Cascade, not nullOnDelete: unlike a community post, a private note has no
            // value to anyone else and nothing to anonymise. Erasure means gone.
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();

            $t->string('paper', 160)->nullable();
            $t->string('topic', 240);
            $t->enum('depth', ['exam_focused', 'detailed', 'revision'])->default('exam_focused');
            // 'both' renders Telugu and English side by side, which is how a student who
            // studies in Telugu but sits an English paper actually revises.
            $t->string('locale', 8)->default('te');

            $t->string('title', 300);
            $t->longText('body');

            // The passages it was built from, titles included, so the "Built from" list
            // survives even after ai_requests is pruned at 180 days.
            $t->json('sources')->nullable();
            $t->decimal('confidence', 3, 2)->nullable();

            // Null when the note was served from cache rather than freshly generated.
            $t->foreignId('ai_request_id')->nullable();

            $t->boolean('is_saved')->default(true);
            $t->timestamps();

            $t->index(['user_id', 'created_at'], 'idx_gennote_user');
            $t->index(['exam_id', 'topic'], 'idx_gennote_topic');
        });

        // Added separately: ai_requests is pruned on a retention schedule, so this must
        // null rather than cascade — losing the cost record must not lose the student's note.
        Schema::table('generated_notes', function (Blueprint $t) {
            $t->foreign('ai_request_id')->references('id')->on('ai_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_notes');
    }
};
