<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identity, profile, consent.
 *
 * Data minimisation is a hard requirement of the DPDP Act 2023 and of Vol 1 ch.11.3:
 * no Aadhaar, no caste certificate uploads, no address beyond district. Date of birth
 * and category exist for exactly one purpose — eligibility matching — and that purpose
 * is stated to the user at the point of collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->uuid()->unique();
            $t->string('name', 120);

            // OTP-first. Phone is the identity; password is optional and usually null.
            $t->string('phone', 15)->unique();
            $t->timestamp('phone_verified_at')->nullable();
            $t->string('email', 190)->nullable()->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();
            $t->rememberToken();

            $t->char('preferred_locale', 5)->default('te');
            $t->unsignedInteger('reputation')->default(0);

            // Strongest trust signal in the community. Manually verified, never self-declared.
            $t->boolean('is_verified_selected')->default(false);
            $t->string('verified_selected_exam', 120)->nullable();

            $t->boolean('is_staff')->default(false);
            $t->boolean('is_banned')->default(false);
            $t->string('ban_reason', 300)->nullable();

            $t->timestamp('last_active_at')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index('last_active_at', 'idx_last_active');
            $t->index('reputation', 'idx_reputation');
            $t->index('is_staff', 'idx_staff');
        });

        Schema::create('profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $t->date('date_of_birth')->nullable();
            $t->enum('gender', ['male', 'female', 'other'])->nullable();
            $t->enum('category', ['general', 'obc', 'sc', 'st', 'ews'])->nullable();
            $t->boolean('is_pwd')->default(false);
            $t->boolean('is_ex_serviceman')->default(false);

            $t->enum('highest_qualification', [
                '10th', '12th', 'iti', 'diploma', 'degree', 'pg', 'btech', 'mbbs', 'phd',
            ])->nullable();
            $t->string('qualification_stream', 100)->nullable();

            $t->char('state', 2)->default('TS');
            $t->string('district', 60)->nullable();

            $t->timestamps();

            $t->index(['highest_qualification', 'category', 'date_of_birth'], 'idx_eligibility');
            $t->index(['state', 'district'], 'idx_domicile');
        });

        Schema::create('user_exam_preferences', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->tinyInteger('priority')->default(1);

            // Integration Map gap 3. The study planner needs to know which exam the user is
            // actually writing next and when — not merely which ones they follow. Asking for
            // this also improves feed ranking and push relevance, so it earns its keep twice.
            $t->date('target_date')->nullable();
            $t->boolean('is_primary')->default(false);

            $t->timestamps();

            $t->primary(['user_id', 'exam_id']);
            $t->index(['user_id', 'is_primary'], 'idx_primary_exam');
        });

        Schema::create('push_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token', 500);
            $t->enum('platform', ['web', 'android', 'ios']);
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });

        // MySQL cannot index a 500-char column in full under utf8mb4; prefix-index it.
        DB::statement('CREATE UNIQUE INDEX uk_token ON push_tokens (token(191))');

        /**
         * Per-type delivery preferences.
         *
         * Integration Map gap 10: the five-pushes-a-day cap was written before the
         * current-affairs digest existed. A new push type must be registered here and
         * compete inside that budget, never be quietly added on top of it.
         */
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 60);
            $t->boolean('push')->default(true);
            $t->boolean('whatsapp')->default(false);
            $t->boolean('email')->default(false);
            $t->timestamps();

            $t->unique(['user_id', 'type'], 'uk_user_type');
        });

        /**
         * DPDP Act 2023: consent is itemised by purpose, given in the user's own language,
         * and the exact version consented to must be recoverable years later. Storing the
         * policy version and a hash of the wording actually shown is what makes it auditable.
         */
        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 80);
            $t->boolean('granted');
            $t->string('policy_version', 20);
            $t->char('locale', 5);
            $t->string('text_hash', 64);
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 300)->nullable();
            $t->timestamp('granted_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'purpose'], 'idx_user_purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('user_exam_preferences');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('users');
    }
};
