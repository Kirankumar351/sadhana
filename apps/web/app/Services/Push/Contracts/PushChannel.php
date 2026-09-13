<?php

declare(strict_types=1);

namespace App\Services\Push\Contracts;

use App\Models\User;
use App\Services\Push\PushMessage;

/**
 * A delivery channel.
 *
 * Two exist: web push via FCM, and WhatsApp. They are not interchangeable — WhatsApp has
 * by far the highest open rate in this market but requires a pre-approved template and
 * costs money per message, while web push is free and unrestricted but only reaches users
 * who granted permission. The dispatcher picks per message type, not per user.
 */
interface PushChannel
{
    public function key(): string;

    /**
     * Can this channel reach this user at all right now?
     */
    public function canReach(User $user): bool;

    /**
     * Returns true when the provider accepted the message.
     *
     * Implementations must NOT throw on a delivery failure. One user's dead token cannot
     * be allowed to abort a broadcast to fifty thousand others.
     */
    public function send(User $user, PushMessage $message): bool;
}
