<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foreign keys that cross module boundaries and therefore cannot be declared inline
 * without forcing an awkward migration order.
 *
 * All of them are nullOnDelete by design: `ai_requests` is pruned on a retention schedule
 * for cost reasons, and an AI-authored answer or a stored evaluation must survive that
 * pruning. Losing the link is acceptable; losing the answer is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $t) {
            $t->foreign('ai_request_id')->references('id')->on('ai_requests')->nullOnDelete();
        });

        Schema::table('answer_evaluations', function (Blueprint $t) {
            $t->foreign('ai_request_id')->references('id')->on('ai_requests')->nullOnDelete();
        });

        Schema::table('posts', function (Blueprint $t) {
            $t->foreign('best_answer_id')->references('id')->on('answers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->dropForeign(['best_answer_id']);
        });

        Schema::table('answer_evaluations', function (Blueprint $t) {
            $t->dropForeign(['ai_request_id']);
        });

        Schema::table('answers', function (Blueprint $t) {
            $t->dropForeign(['ai_request_id']);
        });
    }
};
