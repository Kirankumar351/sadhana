<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user hit 3, 7, 30, 100 or 365 days.
 *
 * An event rather than a direct call, because several things want to react and none of
 * them should be the streak service's concern: a congratulation push, a shareable image
 * for WhatsApp status, an analytics event, and eventually a badge.
 *
 * The listener that matters most is the shareable image. A streak milestone is the moment
 * a user is most willing to tell people what they are doing, and a result card posted to
 * WhatsApp status is the cheapest acquisition channel in the product.
 *
 * TONE IS A CONSTRAINT ON EVERY LISTENER. These users are already under exam stress and
 * family pressure. Celebrate the milestone; never imply that missing tomorrow would waste
 * it. Loss-aversion messaging works on a language-learning app and backfires here.
 */
final class StreakMilestoneReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly int $days,
    ) {}

    /**
     * Whether this milestone is worth interrupting someone for.
     *
     * Three days is worth an in-app note and nothing more. The push budget is five a day
     * across the whole product, and a congratulation competing with a notification
     * deadline reminder should always lose.
     */
    public function warrantsPush(): bool
    {
        return $this->days >= 7;
    }
}
