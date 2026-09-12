<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advertising — the primary revenue engine, per Vol 1 ch.8.2.
 *
 * Direct sales beat programmatic here by 5-10x for one structural reason: we can offer
 * district-level and exam-level targeting. A Karimnagar coaching centre wants Karimnagar
 * Group-2 aspirants and nobody else can sell them exactly that. The targeting columns
 * below are therefore not a nice-to-have, they are the product being sold.
 *
 * Placement discipline (Vol 1 ch.8.2): never a full-screen interstitial on first visit,
 * and never an ad mid-content on a syllabus page. Ads live in the feed, between exam-page
 * sections, and before a download. Breaking this trades the habit loop for a small CPM gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Advertisers as a real CRM object, because direct sales is a relationship business
         * and the same coaching institute buys every notification season for years.
         */
        Schema::create('advertisers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 160);
            $t->string('contact_name', 120)->nullable();
            $t->string('contact_phone', 15)->nullable();
            $t->string('contact_email', 190)->nullable();
            $t->string('gstin', 20)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('category', 60)->nullable();   // coaching | hostel | publisher | local_business
            $t->enum('status', ['lead', 'active', 'churned', 'blocked'])->default('lead');
            $t->text('notes')->nullable();
            $t->foreignId('owned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'category'], 'idx_pipeline');
        });

        Schema::create('ad_campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('advertiser_id')->nullable()->constrained()->nullOnDelete();
            $t->string('advertiser_name', 160);
            $t->string('contact_phone', 15)->nullable();

            $t->string('creative_path', 500)->nullable();
            $t->json('headline')->nullable();
            $t->json('body')->nullable();
            $t->string('target_url', 700);

            $t->enum('placement', ['feed', 'exam_page', 'pre_download', 'quiz_sponsor', 'digest'])
                ->default('feed');

            // The targeting that direct advertisers actually pay a premium for.
            $t->json('target_districts')->nullable();
            $t->json('target_exams')->nullable();
            $t->json('target_locales')->nullable();
            $t->json('target_qualifications')->nullable();

            $t->date('starts_at');
            $t->date('ends_at');
            $t->unsignedInteger('daily_cap')->nullable();
            $t->unsignedInteger('total_impression_cap')->nullable();

            $t->unsignedInteger('amount_paise')->nullable();
            $t->enum('pricing_model', ['flat', 'cpm', 'cpc'])->default('flat');

            $t->enum('status', ['draft', 'pending_review', 'active', 'paused', 'completed'])
                ->default('draft');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'starts_at', 'ends_at'], 'idx_adcampaign_active');
            $t->index('placement', 'idx_placement');
        });

        /**
         * The fastest-growing table in the system.
         *
         * Write raw events here, roll up hourly into ad_event_rollups, and drop partitions
         * older than 90 days. NEVER query this table for a dashboard — at a million
         * impressions a day a single unindexed dashboard query will take the site down on
         * exactly the day it is busiest.
         *
         * Partitioned by month. `ad-events:partition` (scheduled monthly) adds the next
         * partition and drops the expired one. Note the MySQL constraint: every unique key
         * on a partitioned table must contain the partitioning column, which is why the
         * primary key is composite here.
         */
        $isMysql = Schema::getConnection()->getDriverName() === 'mysql';

        Schema::create('ad_events', function (Blueprint $t) use ($isMysql) {
            $t->unsignedBigInteger('id', true);
            $t->unsignedBigInteger('campaign_id');
            $t->enum('event_type', ['impression', 'click']);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('district', 60)->nullable();
            $t->char('locale', 5)->nullable();
            $t->string('placement', 40)->nullable();
            $t->timestamp('occurred_at');

            // MySQL requires every unique key on a partitioned table to contain the
            // partitioning column, hence the composite primary key. SQLite rejects
            // AUTOINCREMENT on a composite primary key outright — and since it does not
            // partition either, the auto-incrementing id alone is the correct key there.
            if ($isMysql) {
                $t->primary(['id', 'occurred_at']);
            }

            $t->index(['campaign_id', 'occurred_at'], 'idx_campaign_time');
        });

        // Monthly partitions, added and dropped by the `ad-events:partition` command.
        // MySQL only; SQLite has no partitioning and the test suite never needs it.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE ad_events
                PARTITION BY RANGE (UNIX_TIMESTAMP(occurred_at)) (
                    PARTITION p_init VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01')),
                    PARTITION p_max  VALUES LESS THAN MAXVALUE
                )
            SQL);
        }

        Schema::create('ad_event_rollups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $t->date('date');
            $t->unsignedTinyInteger('hour');
            $t->string('district', 60)->nullable();
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamps();

            $t->unique(['campaign_id', 'date', 'hour', 'district'], 'uk_rollup');
            $t->index(['campaign_id', 'date'], 'idx_campaign_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_event_rollups');
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('advertisers');
    }
};
