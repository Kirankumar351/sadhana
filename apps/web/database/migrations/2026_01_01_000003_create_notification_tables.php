<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications — the heart of the product, and the table with the highest cost of error.
 *
 * `notifications` is the OWNER of every eligibility criterion, date and fee in the system.
 * Per docs/02-DATA-OWNERSHIP.md only a content lead may write these fields, and only after
 * verifying them against the official PDF. AI may propose into the review queue; it may
 * never promote. A wrong last date costs a student a year.
 *
 * Note the name: Laravel reserves `notifications` for its own database notification
 * channel, which this project does not use. The model is `ExamNotification` so that the
 * distinction is visible in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('url', 700);
            $t->string('parser_class', 160);
            $t->unsignedInteger('frequency_min')->default(30);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->unsignedInteger('consecutive_failures')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['is_active', 'last_run_at'], 'idx_due');
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->string('slug', 200)->unique();
            $t->json('title');
            $t->json('description')->nullable();
            $t->string('organisation', 160);
            $t->enum('job_type', ['government', 'private', 'psu', 'contract'])->default('government');

            // --- vacancy ---
            $t->unsignedInteger('total_vacancies')->nullable();
            $t->json('vacancy_breakdown')->nullable();     // by post and category

            // --- eligibility: drives EligibilityService, which is deterministic ---
            $t->enum('min_qualification', [
                '10th', '12th', 'iti', 'diploma', 'degree', 'pg', 'btech', 'mbbs', 'phd',
            ])->nullable();
            $t->json('qualification_notes')->nullable();
            $t->unsignedTinyInteger('min_age')->nullable();
            $t->unsignedTinyInteger('max_age')->nullable();
            $t->json('age_relaxation')->nullable();        // {"obc":3,"sc":5,"st":5,"pwd":10}
            $t->date('age_reference_date')->nullable();    // the "as on" date — never the apply deadline
            $t->json('allowed_states')->nullable();        // ["TS"] or null for all-India
            $t->json('allowed_districts')->nullable();
            $t->enum('gender_restriction', ['any', 'male', 'female'])->default('any');

            // --- dates ---
            $t->date('notification_date')->nullable();
            $t->date('apply_start_date')->nullable();
            $t->date('apply_end_date')->nullable();
            $t->date('fee_payment_end_date')->nullable();
            $t->date('exam_date')->nullable();
            $t->date('admit_card_date')->nullable();

            // --- money ---
            $t->json('application_fee')->nullable();       // by category
            $t->unsignedInteger('salary_min')->nullable();
            $t->unsignedInteger('salary_max')->nullable();

            // --- provenance and verification ---
            $t->string('official_pdf_url', 700)->nullable();
            $t->string('apply_url', 700)->nullable();
            $t->string('source_url', 700)->nullable();
            $t->foreignId('source_id')->nullable()->constrained('scrape_sources')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();

            $t->enum('status', ['draft', 'pending_review', 'published', 'expired', 'cancelled'])
                ->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('view_count')->default(0);
            $t->unsignedInteger('save_count')->default(0);

            $t->timestamps();
            $t->softDeletes();

            $t->index(['status', 'published_at'], 'idx_status_published');
            $t->index('apply_end_date', 'idx_deadline');
            $t->index(['min_qualification', 'max_age', 'status'], 'idx_eligibility_match');
            $t->index(['job_type', 'status', 'published_at'], 'idx_job_type');
        });

        Schema::create('notification_saves', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $t->timestamp('remind_at')->nullable();
            $t->boolean('reminder_sent')->default(false);
            $t->timestamps();

            $t->primary(['user_id', 'notification_id']);
            $t->index(['remind_at', 'reminder_sent'], 'idx_remind');
        });

        /**
         * One-tap "Report an error" on every notification page, routed to a 2-hour SLA queue.
         * Vol 1 ch.11.2: a visible correction path is worth more to credibility than being
         * right the first time, because being right every time is not achievable.
         */
        Schema::create('notification_error_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('field', 60)->nullable();
            $t->text('note')->nullable();
            $t->enum('status', ['open', 'confirmed', 'dismissed'])->default('open');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_open_reports');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_error_reports');
        Schema::dropIfExists('notification_saves');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('scrape_sources');
    }
};
