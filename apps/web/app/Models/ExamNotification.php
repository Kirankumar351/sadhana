<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Translatable\HasTranslations;

/**
 * A job notification — the heart of the product.
 *
 * Named `ExamNotification` rather than `Notification` because Laravel reserves that name
 * for its own notification channel. The table is `notifications`.
 *
 * THIS MODEL OWNS every eligibility criterion, date and fee in the system. Per
 * docs/02-DATA-OWNERSHIP.md only a content lead may write those fields, and only after
 * verifying them against the official PDF. AI may propose into the review queue; it may
 * never promote. A wrong last date costs a student a year.
 */
class ExamNotification extends Model
{
    /** @use HasFactory<ExamNotificationFactory> */
    use HasFactory, HasTranslations, HasUuids, Searchable, SoftDeletes;

    protected $table = 'notifications';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = [
        'title', 'description', 'qualification_notes',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'vacancy_breakdown' => 'array',
            'age_relaxation' => 'array',
            'allowed_states' => 'array',
            'allowed_districts' => 'array',
            'application_fee' => 'array',
            'notification_date' => 'date',
            'apply_start_date' => 'date',
            'apply_end_date' => 'date',
            'fee_payment_end_date' => 'date',
            'exam_date' => 'date',
            'admit_card_date' => 'date',
            'age_reference_date' => 'date',
            'published_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Not a database column. The feed decorates each row with the result of
     * EligibilityService so the view can render a badge without re-running the check.
     *
     * @var array<string, mixed>|null
     */
    public ?array $eligibility = null;

    // ---------------------------------------------------------------- relations

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ScrapeSource::class, 'source_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function errorReports(): HasMany
    {
        return $this->hasMany(NotificationErrorReport::class, 'notification_id');
    }

    // ---------------------------------------------------------------- scopes

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /**
     * Still applicable: no deadline recorded, or the deadline has not passed.
     * A notification with no `apply_end_date` is kept visible deliberately — an unknown
     * deadline is not the same as an expired one, and hiding it would lose a real job.
     */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->where(
            fn (Builder $q) => $q->whereNull('apply_end_date')->orWhereDate('apply_end_date', '>=', today())
        );
    }

    public function scopeClosingWithin(Builder $q, int $days): Builder
    {
        return $q->whereBetween('apply_end_date', [today(), today()->addDays($days)]);
    }

    // ---------------------------------------------------------------- helpers

    public function daysLeft(): ?int
    {
        if ($this->apply_end_date === null) {
            return null;
        }

        return (int) today()->diffInDays($this->apply_end_date, absolute: false);
    }

    /**
     * Red is reserved strictly for the last three days before a deadline (UI spec), so it
     * never loses its meaning. This is the only place that decision is encoded.
     */
    public function isUrgent(): bool
    {
        $days = $this->daysLeft();

        return $days !== null && $days >= 0 && $days <= 3;
    }

    public function isFresh(): bool
    {
        return $this->published_at !== null && $this->published_at->isToday();
    }

    // ---------------------------------------------------------------- search

    /**
     * One index per locale. Telugu tokenisation differs fundamentally from English, and a
     * shared index makes relevance scoring incoherent for both. The storage cost of
     * duplication is trivial; the relevance cost of sharing is not.
     */
    public function searchableAs(): string
    {
        return 'notifications_'.app()->getLocale();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', $locale),
            'description' => strip_tags((string) $this->getTranslation('description', $locale)),
            'organisation' => $this->organisation,
            'exam_name' => $this->exam?->getTranslation('name', $locale),
            'job_type' => $this->job_type,
            'qualification' => $this->min_qualification,
            'end_date' => $this->apply_end_date?->timestamp,
            'published_at' => $this->published_at?->timestamp,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published';
    }
}
