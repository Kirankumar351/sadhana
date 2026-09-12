<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A quiz question.
 *
 * `correct_index` is the most dangerous column in the database. A fluent question with a
 * wrong key teaches thousands of people something false and they carry it into the exam
 * hall. Every key is verified by a person against a source before the row leaves draft,
 * and a question users dispute is pulled from rotation rather than argued about.
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['question', 'options', 'explanation'];

    protected function casts(): array
    {
        return [
            'is_current_affairs' => 'boolean',
            'is_disputed' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ---------------------------------------------------------------- scopes

    /**
     * Fit to serve: approved, not disputed, and present in the target language.
     *
     * The locale check is the important one. Serving an English-only question inside a
     * Telugu quiz is precisely the silent failure Vol 2 warns about — the user does not
     * report it, they just stop doing the quiz.
     */
    public function scopeServable(Builder $q, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();

        return $q->whereNotNull('approved_at')
            ->where('is_disputed', false)
            ->whereNotNull("question->{$locale}");
    }

    /**
     * Rotate the pool evenly rather than serving the same easy questions forever.
     */
    public function scopeLeastServed(Builder $q): Builder
    {
        return $q->orderBy('times_served')->inRandomOrder();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Options for a locale, falling back so a half-translated question still renders
     * rather than showing an empty list.
     *
     * @return list<string>
     */
    public function optionsFor(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $options = $this->getTranslation('options', $locale, useFallbackLocale: true);

        if (is_string($options)) {
            $options = json_decode($options, true);
        }

        return is_array($options) ? array_values($options) : [];
    }

    public function isCorrect(int $answerIndex): bool
    {
        return $answerIndex === (int) $this->correct_index;
    }

    /**
     * Observed difficulty, as a percentage correct. Null until the question has been served
     * enough times for the number to mean anything — showing "100% correct" after two
     * attempts is worse than showing nothing.
     */
    public function accuracy(): ?float
    {
        if ($this->times_served < 20) {
            return null;
        }

        return round($this->times_correct / $this->times_served * 100, 1);
    }
}
