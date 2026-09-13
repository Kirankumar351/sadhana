<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/**
 * The schedule.
 *
 * Everything here runs in Asia/Kolkata. A server on UTC would publish the "daily" quiz at
 * 12:15 PM IST and send the 7 AM push in the middle of the afternoon, which is the kind of
 * bug that is obvious in production and invisible in review.
 */

// ---------------------------------------------------------------- ingestion

/**
 * Sources carry their own frequency, so this only dispatches what is actually due.
 * Checking a district collectorate page every 30 minutes for content that changes twice a
 * year is how a polite crawler becomes a blocked one.
 */
Schedule::command('scrape:run')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ---------------------------------------------------------------- the daily habit

/**
 * Publish at 06:45, push at 07:00.
 *
 * The fifteen-minute gap is deliberate: never send a notification for content that has not
 * finished writing. A push landing on a half-built quiz is worse than a push that is
 * fifteen minutes late.
 */
Schedule::command('quiz:publish')
    ->dailyAt('06:45')
    ->timezone('Asia/Kolkata');

// ---------------------------------------------------------------- corpus

/**
 * Re-embed anything marked stale overnight.
 *
 * At 02:00 because embedding is the expensive part of a reindex and it should never
 * compete with daytime traffic — least of all on a notification day.
 */
Schedule::command('corpus:reindex --stale')
    ->dailyAt('02:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

// ---------------------------------------------------------------- housekeeping

/**
 * Expire notifications whose deadline has passed.
 *
 * At 00:30 rather than midnight: a user submitting an application at 23:59 on the last day
 * should not watch the page change under them.
 */
Schedule::command('notifications:expire')
    ->dailyAt('00:30')
    ->timezone('Asia/Kolkata');

Schedule::command('sitemap:generate')
    ->dailyAt('02:30')
    ->timezone('Asia/Kolkata');
