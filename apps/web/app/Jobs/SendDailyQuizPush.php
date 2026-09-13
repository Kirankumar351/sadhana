<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyQuiz;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use App\Services\Push\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The 07:00 push. This is the habit loop.
 *
 * Vol 1 names D7 retention above 20% as the highest-risk assumption in the entire business
 * plan, and this job is the mechanism that has to deliver it. Everything else in the
 * product is downstream of people opening it at 7 AM because they always do.
 *
 * IT REFUSES TO SEND IF THE QUIZ IS NOT ACTUALLY READY. Publishing runs at 06:45 and this
 * at 07:00 precisely so there is a gap; if that gap was not enough, the correct behaviour
 * is silence. A push that lands on a half-built quiz teaches people the product is broken,
 * and that costs far more than one missed morning.
 */
class SendDailyQuizPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly ?string $date = null)
    {
        $this->onQueue('push');
    }

    public function handle(PushDispatcher $dispatcher): void
    {
        $quiz = $this->date
            ? DailyQuiz::query()->whereDate('quiz_date', $this->date)->first()
            : DailyQuiz::today();

        if ($quiz === null || ! $quiz->isPublished()) {
            Log::warning('push.daily_quiz.not_ready', ['date' => $this->date ?? today()->toDateString()]);

            return;
        }

        if (count($quiz->question_ids ?? []) < 10) {
            Log::warning('push.daily_quiz.incomplete');

            return;
        }

        /**
         * Only users who have actually used the product.
         *
         * Someone who signed up three months ago and never returned does not want a 7 AM
         * notification; they want to be left alone. Pushing to dormant accounts is how a
         * sender reputation is spent for nothing.
         */
        $users = User::query()
            ->where('is_banned', false)
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '>=', now()->subDays(30))
            ->whereHas('pushTokens', fn ($q) => $q->where('is_active', true))
            ->lazyById(1000);

        $result = $dispatcher->broadcast($users, fn (User $user): PushMessage => $this->messageFor($user));

        Log::info('push.daily_quiz.sent', $result);
    }

    private function messageFor(User $user): PushMessage
    {
        $locale = $user->preferred_locale ?: config('locales.default');

        return new PushMessage(
            type: 'daily_quiz',
            title: $locale === 'te' ? 'రోజు ప్రశ్న' : "Today's quiz",
            // Deliberately warm and specific, never guilt-inducing. These users are
            // already under exam stress and family pressure; loss-aversion copy works on
            // a language-learning app and backfires here.
            body: $locale === 'te'
                ? '10 ప్రశ్నలు సిద్ధంగా ఉన్నాయి. మీ స్ట్రీక్ కొనసాగించండి.'
                : '10 questions are ready. Keep your streak going.',
            url: route('quiz.today', ['locale' => $locale]),
            locale: $locale,
            whatsappTemplate: 'daily_quiz_reminder',
            templateParams: [$user->name],
        );
    }
}
