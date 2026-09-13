<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyQuiz;
use App\Models\Question;
use App\Notifications\QuizPoolLow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Build tomorrow's ten questions.
 *
 * Runs at 06:45, fifteen minutes before the 07:00 push. That gap is deliberate: never send
 * a notification for content that has not finished writing.
 *
 * THE MIX IS 5 CURRENT AFFAIRS, 3 SUBJECT, 2 PREVIOUS-YEAR. Current affairs is the binding
 * constraint on the whole retention engine — it cannot be written months in advance, which
 * is why the news pipeline exists and why it moved earlier in the build order.
 *
 * NEVER PUBLISH A SHORT QUIZ. A user who opens "today's 10 questions" and finds six learns
 * that the product is unreliable, and that impression is much harder to undo than a missed
 * day. If the pool cannot fill a full quiz, this fails loudly and alerts the content team
 * instead of shipping something broken.
 */
class PublishDailyQuiz extends Command
{
    protected $signature = 'quiz:publish {--date= : Build for a specific date} {--dry-run}';

    protected $description = 'Assemble and publish the daily quiz';

    private const SIZE = 10;

    /** Days a question must sit out before it can be served again. */
    private const COOLDOWN_DAYS = 60;

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::parse(today());

        if (DailyQuiz::query()->whereDate('quiz_date', $date)->exists()) {
            $this->warn('Already published for '.$date->toDateString());

            return self::SUCCESS;
        }

        $recent = $this->recentlyUsedIds();

        $questions = collect()
            ->merge($this->pick(currentAffairs: true, count: 5, exclude: $recent))
            ->merge($this->pick(currentAffairs: false, count: 3, exclude: $recent))
            ->merge($this->pickPreviousYear(2, $recent))
            ->unique('id');

        // Top up from anything servable rather than shipping a short quiz. A slightly
        // off-mix quiz is a far better outcome than a broken one.
        if ($questions->count() < self::SIZE) {
            $questions = $questions->merge(
                $this->pick(null, self::SIZE - $questions->count(), $recent->merge($questions->pluck('id')))
            )->unique('id');
        }

        if ($questions->count() < self::SIZE) {
            $this->error("Only {$questions->count()} servable questions available. Not publishing a short quiz.");

            Notification::route('mail', config('app.ops_email'))
                ->notify(new QuizPoolLow($questions->count(), self::SIZE));

            return self::FAILURE;
        }

        $ids = $questions->take(self::SIZE)->pluck('id')->all();

        if ($this->option('dry-run')) {
            $this->table(
                ['id', 'subject', 'current affairs'],
                $questions->take(self::SIZE)->map(fn (Question $q): array => [
                    $q->id, $q->subject ?? '—', $q->is_current_affairs ? 'yes' : 'no',
                ])->all(),
            );

            return self::SUCCESS;
        }

        DailyQuiz::create([
            'quiz_date' => $date,
            'question_ids' => $ids,
            'published_at' => now(),
        ]);

        Question::query()->whereIn('id', $ids)->increment('times_served');

        $this->info('Published '.count($ids).' questions for '.$date->toDateString());

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $exclude
     * @return Collection<int, Question>
     */
    private function pick(?bool $currentAffairs, int $count, Collection $exclude): Collection
    {
        return Question::query()
            // Approved, not disputed, and present in Telugu. Serving an English-only
            // question inside a Telugu quiz is the silent failure that loses users: they
            // do not report it, they just stop doing the quiz.
            ->servable('te')
            ->when($currentAffairs !== null, fn ($q) => $q->where('is_current_affairs', $currentAffairs))
            ->whereNotIn('id', $exclude)
            ->leastServed()
            ->limit($count)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $exclude
     * @return Collection<int, Question>
     */
    private function pickPreviousYear(int $count, Collection $exclude): Collection
    {
        return Question::query()
            ->servable('te')
            ->whereNotNull('source_year')
            ->whereNotIn('id', $exclude)
            ->leastServed()
            ->limit($count)
            ->get();
    }

    /**
     * Questions used in the last 60 days.
     *
     * Without a cooldown the rotation serves the same easy questions repeatedly, the
     * accuracy statistics stop meaning anything, and regular users notice immediately.
     *
     * @return Collection<int, int>
     */
    private function recentlyUsedIds(): Collection
    {
        return DailyQuiz::query()
            ->where('quiz_date', '>=', today()->subDays(self::COOLDOWN_DAYS))
            ->pluck('question_ids')
            ->flatten()
            ->unique()
            ->values();
    }
}
