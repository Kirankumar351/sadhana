<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExamNotification;
use Illuminate\Console\Command;

/**
 * Move notifications past their deadline out of the live feed.
 *
 * Runs at 00:30 rather than midnight, so someone submitting an application at 23:59 on the
 * last day does not watch the page change under them.
 *
 * EXPIRED IS NOT DELETED. The page stays reachable at its URL, because it still ranks, it
 * still carries the syllabus and cutoff context people search for, and an expired
 * notification answers "did I miss it?" — which is a real question people ask. It simply
 * stops occupying space in the feed.
 */
class ExpireNotifications extends Command
{
    protected $signature = 'notifications:expire';

    protected $description = 'Mark notifications whose application deadline has passed';

    public function handle(): int
    {
        $expired = ExamNotification::query()
            ->where('status', 'published')
            ->whereNotNull('apply_end_date')
            ->whereDate('apply_end_date', '<', today())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expired} notification(s).");

        return self::SUCCESS;
    }
}
