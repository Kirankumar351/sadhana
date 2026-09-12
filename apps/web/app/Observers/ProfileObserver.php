<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Profile;
use App\Models\StudyPlan;
use Illuminate\Support\Facades\Cache;

/**
 * A changed profile invalidates everything derived from it.
 *
 * A user correcting their date of birth or adding a qualification changes the eligibility
 * verdict on every notification in the feed at once. Serving the old answers would be
 * worse than never having computed them, because the user just told us we were wrong and
 * the page would keep insisting otherwise.
 */
class ProfileObserver
{
    public function saved(Profile $profile): void
    {
        Cache::forget("eligibility:{$profile->user_id}");

        /**
         * Mark any study plan stale rather than deleting it.
         *
         * The plan stays readable until a new one is generated — a student mid-week with
         * no plan at all is worse off than one following a slightly outdated plan, and
         * generation costs real money so it should happen on demand, not on every edit.
         */
        if ($profile->wasChanged(['highest_qualification', 'date_of_birth', 'category'])) {
            StudyPlan::query()
                ->where('user_id', $profile->user_id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);
        }
    }
}
