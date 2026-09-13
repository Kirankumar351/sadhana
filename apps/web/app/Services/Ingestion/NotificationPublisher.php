<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\ExamNotification;
use App\Models\User;

/**
 * The one place a notification goes live.
 *
 * Two screens publish — the review queue and the notifications list — and both must record
 * the same thing: who verified it and when. That attribution is not bookkeeping. When a
 * date turns out to be wrong, the first question is who read the source and what they read,
 * and a publish path that skips the signature makes that unanswerable.
 *
 * The alert to users is NOT dispatched here. CorpusObserver fires it on the transition into
 * `published`, which means it fires however the row got there — an admin action, a command,
 * a fix in tinker. Dispatching here as well would send it twice.
 */
final class NotificationPublisher
{
    public function publish(ExamNotification $notification, ?User $verifier): ExamNotification
    {
        $notification->update([
            'status' => 'published',
            // Preserved on a re-publish: the original publication time is what the feed
            // orders by and what "new today" means to a user.
            'published_at' => $notification->published_at ?? now(),
            'verified_by' => $verifier?->id,
            'verified_at' => now(),
        ]);

        return $notification;
    }

    /**
     * Pull it back the moment an error is reported.
     *
     * Correcting fast costs less trust than being right first time, so this is deliberately
     * a one-click path with no confirmation cost — hesitating here is the expensive move.
     */
    public function unpublish(ExamNotification $notification): ExamNotification
    {
        $notification->update(['status' => 'draft']);

        return $notification;
    }
}
