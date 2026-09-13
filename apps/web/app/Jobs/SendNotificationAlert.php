<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExamNotification;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\Push\PushDispatcher;
use App\Services\Push\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A notification was published. Tell the people it is actually for.
 *
 * This is the single highest-leverage message the product sends, and the one where getting
 * the targeting wrong is most expensive in both directions: miss someone and they find out
 * from Telegram, spam everyone and they mute us before the next one.
 *
 * TARGETING IS ELIGIBILITY-AWARE, NOT JUST INTEREST-AWARE.
 *
 * Someone following Group 2 who is over the age limit does not want to be woken up about a
 * job they cannot apply for. The eligibility engine already knows; using it here is the
 * difference between a useful alert and noise. People who are `partial` still get told,
 * because they may hold a relaxation we do not know about — the same reason the feed
 * labels rather than hides.
 */
class SendNotificationAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('push');
    }

    public function handle(
        PushDispatcher $dispatcher,
        EligibilityService $eligibility,
    ): void {
        $notification = ExamNotification::query()
            ->with('exam')
            ->find($this->notificationId);

        // Never alert about something unpublished or already closed. A job that arrives
        // after its deadline is worse than no alert at all.
        if ($notification === null
            || $notification->status !== 'published'
            || ($notification->apply_end_date?->isPast() ?? false)) {
            return;
        }

        $users = User::query()
            ->where('is_banned', false)
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '>=', now()->subDays(60))
            ->when(
                $notification->exam_id !== null,
                // Followers of this exam first. When a notification has no exam attached
                // it is broadcast to everyone active, which is rare and deliberate.
                fn ($q) => $q->whereHas('examPreferences', fn ($q) => $q->where('exams.id', $notification->exam_id)),
            )
            ->with('profile')
            ->lazyById(1000);

        $skippedIneligible = 0;

        $eligibleUsers = (function () use ($users, $eligibility, $notification, &$skippedIneligible) {
            foreach ($users as $user) {
                $verdict = $eligibility->check($notification, $user->profile);

                // 'unknown' means we have no profile to judge by — those users still get
                // told, because the alternative is hiding a job from someone purely
                // because they have not filled in a form yet.
                if ($verdict['status'] === EligibilityService::STATUS_NOT_ELIGIBLE) {
                    $skippedIneligible++;

                    continue;
                }

                yield $user;
            }
        })();

        $result = $dispatcher->broadcast(
            $eligibleUsers,
            fn (User $user): PushMessage => $this->messageFor($user, $notification),
        );

        Log::info('push.notification_alert.sent', [
            'notification' => $notification->slug,
            'skipped_ineligible' => $skippedIneligible,
            ...$result,
        ]);
    }

    private function messageFor(User $user, ExamNotification $n): PushMessage
    {
        $locale = $user->preferred_locale ?: config('locales.default');
        $title = (string) $n->getTranslation('title', $locale, true);

        $vacancies = $n->total_vacancies !== null
            ? number_format($n->total_vacancies).($locale === 'te' ? ' ఖాళీలు' : ' posts')
            : null;

        $lastDate = $n->apply_end_date !== null
            ? ($locale === 'te' ? 'చివరి తేదీ ' : 'Last date ').$n->apply_end_date->format('d M')
            : null;

        return new PushMessage(
            type: 'new_notification',
            title: $title,
            // Vacancies and the deadline are the two facts that decide whether someone
            // taps. Putting them in the body saves a page load on a 3G connection.
            body: implode(' · ', array_filter([$vacancies, $lastDate])) ?: $n->organisation,
            url: route('notifications.show', ['locale' => $locale, 'slug' => $n->slug]),
            locale: $locale,
            whatsappTemplate: 'new_notification',
            templateParams: array_values(array_filter([$title, $lastDate])),
        );
    }
}
