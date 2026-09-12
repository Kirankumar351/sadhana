<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ExamNotification;
use App\Models\Question;
use App\Models\QuizAttempt;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The numbers the team should see first thing in the morning.
 *
 * Chosen against Vol 1's metric tree, not against what is easy to query. In particular the
 * North Star is weekly active aspirants who completed a MEANINGFUL ACTION — a quiz
 * attempt, a download, a doubt, a saved job — never signups or pageviews, because those
 * can be bought and they lie.
 *
 * Each stat carries the threshold it is judged against, so a number is never shown without
 * the context that says whether it is good.
 */
class OperationsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            $this->dailyActive(),
            $this->reviewQueue(),
            $this->quizPool(),
            $this->teluguGap(),
        ];
    }

    private function dailyActive(): Stat
    {
        $today = QuizAttempt::query()->whereDate('created_at', today())->distinct('user_id')->count('user_id');
        $yesterday = QuizAttempt::query()->whereDate('created_at', today()->subDay())->distinct('user_id')->count('user_id');

        $change = $yesterday > 0 ? round(($today - $yesterday) / $yesterday * 100) : 0;

        return Stat::make('Quiz completions today', (string) $today)
            ->description($yesterday > 0 ? "{$change}% vs yesterday" : 'No comparison yet')
            ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($change >= 0 ? 'success' : 'danger');
    }

    /**
     * Review queue depth against the 2-hour SLA.
     *
     * Publish latency is tracked at a 60-minute p90 target because being first to publish a
     * breaking notification matters enormously for search ranking — and because a student
     * who hears it on Telegram first has no reason to open us.
     */
    private function reviewQueue(): Stat
    {
        $pending = ExamNotification::query()->where('status', 'pending_review')->count();

        $overdue = ExamNotification::query()
            ->where('status', 'pending_review')
            ->where('created_at', '<', now()->subHours(2))
            ->count();

        return Stat::make('Awaiting review', (string) $pending)
            ->description($overdue > 0 ? "{$overdue} past the 2-hour SLA" : 'All within SLA')
            ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
            ->color($overdue > 0 ? 'danger' : 'success');
    }

    /**
     * Days of quiz left in the approved pool.
     *
     * This is the metric that quietly kills the retention engine. The daily quiz needs ten
     * verified, Telugu-complete questions every single morning, and current affairs cannot
     * be written months ahead. Running dry is not recoverable in a day.
     */
    private function quizPool(): Stat
    {
        $servable = Question::query()
            ->whereNotNull('approved_at')
            ->where('is_disputed', false)
            ->whereNotNull('question->te')
            ->count();

        $days = intdiv($servable, 10);

        return Stat::make('Quiz pool', $servable.' questions')
            ->description($days.' days of quiz at 10 a day')
            ->descriptionIcon('heroicon-m-clipboard-document-list')
            ->color(match (true) {
                $days < 7 => 'danger',
                $days < 30 => 'warning',
                default => 'success',
            });
    }

    /**
     * Questions with no Telugu.
     *
     * Telugu-first is the entire differentiation. A question that exists only in English is
     * invisible to the majority of users, and worse, it silently shrinks the pool the daily
     * quiz can draw from without anyone noticing why.
     */
    private function teluguGap(): Stat
    {
        $missing = Question::query()->whereNull('question->te')->count();
        $total = max(Question::query()->count(), 1);
        $percent = round($missing / $total * 100);

        return Stat::make('Questions with no Telugu', (string) $missing)
            ->description($percent.'% of the bank')
            ->descriptionIcon('heroicon-m-language')
            ->color($missing === 0 ? 'success' : ($percent > 20 ? 'danger' : 'warning'));
    }
}
