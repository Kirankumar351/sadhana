<?php

declare(strict_types=1);

use App\Jobs\SendDailyQuizPush;
use App\Jobs\SendDeadlineReminders;
use App\Jobs\SendStreakReminder;
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

Schedule::job(new SendDailyQuizPush)
    ->dailyAt('07:00')
    ->timezone('Asia/Kolkata');

/**
 * The 21:00 nudge, only for people who have a streak and have NOT been active
 * today. Reminding someone who already did the quiz this morning is the fastest
 * way to get the whole channel muted.
 */
Schedule::job(new SendStreakReminder)
    ->dailyAt('21:00')
    ->timezone('Asia/Kolkata');

/**
 * Deadline reminders at 09:00, when someone can actually act on them. The same
 * message at 23:00 is an anxiety generator rather than a service.
 */
Schedule::job(new SendDeadlineReminders)
    ->dailyAt('09:00')
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
