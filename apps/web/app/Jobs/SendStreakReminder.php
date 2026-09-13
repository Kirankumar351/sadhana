<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Push\PushDispatcher;
use App\Services\Push\PushMessage;
use App\Services\StreakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The 21:00 nudge, for people who have a streak going and have not been active today.
 *
 * DELIBERATELY NOT SENT TO EVERYONE WITH A STREAK. Reminding someone who already did the
 * quiz this morning is the fastest way to get the whole channel muted — they learn our
 * notifications are not worth reading, and that lesson applies to every future message
 * including the one about a job closing tomorrow.
 *
 * The copy mentions the freeze when the user has one. Knowing a safety net exists is what
 * stops someone abandoning a 40-day streak after a single bad evening, and an abandoned
 * streak almost never restarts.
 */
class SendStreakReminder implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue('push');
    }

    public function handle(StreakService $streaks, PushDispatcher $dispatcher): void
    {
        $sent = 0;

        foreach ($streaks->atRiskToday() as $streak) {
            $user = $streak->user;

            if ($user === null || $user->is_banned) {
                continue;
            }

            $locale = $user->preferred_locale ?: config('locales.default');
            $days = $streak->current_streak;

            $body = match (true) {
                $streak->freezes_left > 0 => $locale === 'te'
                    ? "మీకు ఈ నెల ఒక ఫ్రీజ్ ఉంది — కానీ ఈరోజు క్విజ్ చేస్తే {$days} రోజుల స్ట్రీక్ కొనసాగుతుంది."
                    : "You have a freeze left this month — but today's quiz keeps your {$days}-day streak going.",
                default => $locale === 'te'
                    ? "{$days} రోజుల స్ట్రీక్. 10 ప్రశ్నలు, రెండు నిమిషాలు."
                    : "{$days}-day streak. Ten questions, two minutes.",
            };

            $ok = $dispatcher->send($user, new PushMessage(
                type: 'streak',
                title: $locale === 'te' ? "🔥 {$days} రోజులు" : "🔥 {$days} days",
                body: $body,
                url: route('quiz.today', ['locale' => $locale]),
                locale: $locale,
            ));

            if ($ok) {
                $sent++;
            }
        }

        Log::info('push.streak_reminder.sent', ['sent' => $sent]);
    }
}
