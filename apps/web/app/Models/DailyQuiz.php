<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DailyQuizFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The 10 questions for one day.
 *
 * Published at 06:45, pushed at 07:00. The fifteen-minute gap is deliberate: never send a
 * notification for content that has not finished writing.
 *
 * The mix is 5 current affairs, 3 static subject, 2 previous-year. Current affairs is the
 * binding constraint — it cannot be written months ahead, which is why the news pipeline
 * exists and why it moved earlier in the build order.
 */
class DailyQuiz extends Model
{
    /** @use HasFactory<DailyQuizFactory> */
    use HasFactory;

    protected $table = 'daily_quizzes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quiz_date' => 'date',
            'question_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * The questions, in the stored order.
     *
     * whereIn does not preserve order, and the order matters — the quiz is built as a
     * sequence with a difficulty ramp, so the list is re-sorted to match question_ids.
     *
     * @return Collection<int, Question>
     */
    public function questions(): Collection
    {
        $ids = $this->question_ids ?? [];

        if ($ids === []) {
            return new Collection;
        }

        $byId = Question::query()->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(
            array_map(static fn ($id) => $byId->get($id), $ids)
        )));
    }

    public static function today(): ?self
    {
        return static::query()->whereDate('quiz_date', today())->first();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function attemptFor(?User $user): ?QuizAttempt
    {
        if ($user === null) {
            return null;
        }

        return $this->attempts()->where('user_id', $user->id)->first();
    }
}
