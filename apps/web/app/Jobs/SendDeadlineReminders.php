<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExamNotification;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use App\Services\Push\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Remind people about jobs they saved, before the deadline closes.
 *
 * THE HIGHEST-PRIORITY MESSAGE THE PRODUCT SENDS. It is the only type allowed through a
 * full daily budget, because the cost of silence here is that somebody misses an
 * application they had already decided to make — a different order of harm from missing a
 * quiz nudge.
 *
 * Two reminders per saved job: three days out, then one day out. Three days is enough time
 * to gather documents and pay the fee; one day is the last honest moment to act. A third
 * reminder adds nothing and turns a useful service into nagging.
 */
class SendDeadlineReminders implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue('push');
    }

    public function handle(PushDispatcher $dispatcher): void
    {
        $sent = 0;

        foreach ([3, 1] as $daysOut) {
            $target = today()->addDays($daysOut);

            $saves = DB::table('notification_saves')
                ->join('notifications', 'notifications.id', '=', 'notification_saves.notification_id')
                ->join('users', 'users.id', '=', 'notification_saves.user_id')
                ->whereDate('notifications.apply_end_date', $target)
                ->where('notifications.status', 'published')
                ->where('users.is_banned', false)
                ->whereNull('users.deleted_at')
                ->select('notification_saves.user_id', 'notification_saves.notification_id')
                ->cursor();

            foreach ($saves as $save) {
                $user = User::find($save->user_id);
                $notification = ExamNotification::find($save->notification_id);

                if ($user === null || $notification === null) {
                    continue;
                }

                $locale = $user->preferred_locale ?: config('locales.default');
                $title = (string) $notification->getTranslation('title', $locale, true);

                $body = $daysOut === 1
                    ? ($locale === 'te' ? 'రేపే చివరి తేదీ. ఇంకా దరఖాస్తు చేయలేదా?' : 'Last date is tomorrow. Have you applied?')
                    : ($locale === 'te' ? '3 రోజుల్లో దరఖాస్తు ముగుస్తుంది.' : 'Applications close in 3 days.');

                $ok = $dispatcher->send($user, new PushMessage(
                    type: 'deadline',
                    title: $title,
                    body: $body,
                    url: route('notifications.show', ['locale' => $locale, 'slug' => $notification->slug]),
                    locale: $locale,
                    whatsappTemplate: 'deadline_reminder',
                    templateParams: [$title, $notification->apply_end_date->format('d M Y')],
                ));

                if ($ok) {
                    $sent++;
                }
            }
        }

        Log::info('push.deadline_reminders.sent', ['sent' => $sent]);
    }
}
