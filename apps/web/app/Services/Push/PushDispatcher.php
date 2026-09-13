<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\User;
use App\Services\Push\Channels\WebPushChannel;
use App\Services\Push\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

/**
 * The single place a notification is sent from.
 *
 * Everything goes through here so the daily cap, the opt-in check and the channel choice
 * cannot be bypassed by a feature in a hurry. A job that talks to a channel directly is a
 * job that will eventually push a sixth notification to someone who has already had five.
 *
 * CHANNEL CHOICE IS PER MESSAGE TYPE, NOT PER USER.
 *
 * Web push is free and goes first for everything. WhatsApp has by far the best open rate
 * in this market but costs money per message and can get the business account restricted
 * if used carelessly, so it is reserved for messages where silence genuinely costs the
 * user something — a closing deadline, or a job they are eligible for.
 */
final class PushDispatcher
{
    public function __construct(
        private readonly PushBudget $budget,
        private readonly WebPushChannel $web,
        private readonly WhatsAppChannel $whatsapp,
    ) {}

    /**
     * Send one message to one user, respecting the budget.
     *
     * Returns false when nothing was sent, which is a normal outcome and not an error: the
     * user may have opted out, hit their cap, or have no reachable channel.
     */
    public function send(User $user, PushMessage $message): bool
    {
        if ($user->is_banned) {
            return false;
        }

        if (! $this->budget->allows($user, $message->type)) {
            return false;
        }

        $sent = $this->web->send($user, $message);

        /**
         * WhatsApp is a fallback for high-value messages, not a duplicate.
         *
         * Only used when web push did not land — an unreachable user, a revoked
         * permission, a dead token. Sending both would mean paying twice to interrupt the
         * same person twice about the same thing, which is how a channel people value
         * becomes a channel people block.
         */
        if (! $sent && $this->shouldTryWhatsApp($message)) {
            $sent = $this->whatsapp->send($user, $message);
        }

        if ($sent) {
            $this->budget->record($user, $message->type);
        }

        return $sent;
    }

    /**
     * Send to many users, in waves.
     *
     * WAVES MATTER ON NOTIFICATION DAY. Pushing to 300,000 people at once means 300,000
     * of them open the site within the same two minutes, which is a self-inflicted
     * denial of service on the exact day the product is most valuable. Vol 2 specifies
     * staggered waves of 50,000; this sleeps between them so the traffic curve has a
     * shoulder rather than a spike.
     *
     * @param  LazyCollection<int, User>|iterable<User>  $users
     * @param  callable(User): PushMessage  $build
     * @return array{sent: int, skipped: int}
     */
    public function broadcast(iterable $users, callable $build, int $waveSize = 50_000, int $pauseSeconds = 20): array
    {
        $sent = 0;
        $skipped = 0;
        $inWave = 0;

        foreach ($users as $user) {
            $this->send($user, $build($user)) ? $sent++ : $skipped++;

            if (++$inWave >= $waveSize) {
                Log::info('push.wave.complete', ['sent' => $sent, 'skipped' => $skipped]);

                sleep($pauseSeconds);
                $inWave = 0;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function shouldTryWhatsApp(PushMessage $message): bool
    {
        return in_array($message->type, ['deadline', 'new_notification'], true)
            && $message->whatsappTemplate !== null;
    }
}
