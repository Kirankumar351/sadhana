<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailyQuiz;
use App\Models\QuizAttempt;
use App\Models\Streak;
use App\Models\User;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Redis;

/**
 * The daily quiz — the retention engine.
 *
 * Vol 1 names D7 retention above 20% as the highest-risk assumption in the whole business
 * plan. This is the mechanism that has to deliver it.
 */
class QuizController extends Controller
{
    public function today(): View
    {
        $quiz = DailyQuiz::today();

        return view('quiz.today', [
            'seo' => SeoBuilder::forRoute(
                'quiz.today',
                __("Today's quiz"),
                __('Ten free questions every morning at 7 AM, in Telugu and English.'),
            ),
            'quiz' => $quiz,
            'questions' => $quiz?->questions() ?? collect(),
            'existing' => $quiz?->attemptFor(auth()->user()),
        ]);
    }

    public function result(string $locale, QuizAttempt $attempt): View
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        return view('quiz.result', [
            'seo' => SeoBuilder::forRoute('quiz.today', __('Your result'), __('Your daily quiz result.')),
            'attempt' => $attempt->load('dailyQuiz'),
            'streak' => auth()->user()->streak,
        ]);
    }

    /**
     * Weekly leaderboard, read from a Redis sorted set so this stays O(log n) instead of
     * a table scan — it is one of the pages people refresh most.
     *
     * Falls back to the database when Redis is unavailable, because a missing leaderboard
     * should degrade, not 500.
     */
    public function leaderboard(): View
    {
        $week = now()->format('o-W');
        $top = [];

        try {
            $raw = Redis::zrevrange("leaderboard:streak:{$week}", 0, 49, ['withscores' => true]);

            foreach ($raw as $userId => $score) {
                $top[] = ['user_id' => (int) $userId, 'streak' => (int) $score];
            }
        } catch (\Throwable) {
            $top = Streak::query()
                ->orderByDesc('current_streak')
                ->limit(50)
                ->get()
                ->map(fn ($s) => ['user_id' => $s->user_id, 'streak' => $s->current_streak])
                ->all();
        }

        $users = User::query()
            ->whereIn('id', array_column($top, 'user_id'))
            ->get(['id', 'name'])
            ->keyBy('id');

        return view('quiz.leaderboard', [
            'seo' => SeoBuilder::forRoute('quiz.leaderboard', __('Leaderboard'), __('This week\'s longest streaks.')),
            'rows' => $top,
            'users' => $users,
        ]);
    }
}
