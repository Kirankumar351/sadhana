<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two small columns the ingestion pipeline needed.
 *
 * `notifications.registration_url` — applying to most boards is two steps on two different
 * hosts: a one-time registration (TGPSC OTR, APPSC OTPR) and then the application. A draft
 * with only an apply link sends a first-time candidate to a login form they cannot use.
 * Owned by `notifications` like every other link; proposed by a scraper into a draft and
 * confirmed by the reviewer with the rest. Not personal data, not money, not translatable.
 *
 * `scrape_sources.last_summary` — "found 9, queued 3, skipped 6 already closed". Without it
 * the admin screen shows only a timestamp, and a run that finds nothing looks identical to
 * a run that is broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $t) {
            $t->string('registration_url', 700)->nullable()->after('apply_url');
        });

        Schema::table('scrape_sources', function (Blueprint $t) {
            $t->string('last_summary', 500)->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $t) {
            $t->dropColumn('registration_url');
        });

        Schema::table('scrape_sources', function (Blueprint $t) {
            $t->dropColumn('last_summary');
        });
    }
};
