<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A user.
 *
 * OTP-first: the phone number is the identity and `password` is usually null. Email is
 * optional and mostly unused — this audience does not check email, which is why Vol 2
 * reserves it for a weekly digest and nothing else.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * `uuid` is what appears in public URLs; the auto-increment id stays internal so we
     * never leak how many users we have.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'is_verified_selected' => 'boolean',
            'is_staff' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    /**
     * Exams the user follows. `target_date` and `is_primary` ride on the pivot — the
     * study planner needs to know which exam they are actually writing next, not merely
     * which ones they watch.
     */
    public function examPreferences(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'user_exam_preferences')
            ->withPivot(['priority', 'target_date', 'is_primary'])
            ->withTimestamps();
    }

    public function savedNotifications(): BelongsToMany
    {
        return $this->belongsToMany(ExamNotification::class, 'notification_saves', 'user_id', 'notification_id')
            ->withPivot(['remind_at', 'reminder_sent'])
            ->withTimestamps();
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    public function studyPlans(): HasMany
    {
        return $this->hasMany(StudyPlan::class);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The exam the study planner targets. Falls back to the highest-priority followed
     * exam so a user who never set a primary still gets a usable plan.
     */
    public function primaryExam(): ?Exam
    {
        return $this->examPreferences()
            ->orderByDesc('user_exam_preferences.is_primary')
            ->orderBy('user_exam_preferences.priority')
            ->first();
    }

    public function hasCompletedProfile(): bool
    {
        $p = $this->profile;

        return $p !== null
            && $p->date_of_birth !== null
            && $p->highest_qualification !== null;
    }

    /**
     * Eligibility cannot be computed without a date of birth and a qualification. The UI
     * shows "add your details" rather than a misleading badge when this is false.
     */
    public function canCheckEligibility(): bool
    {
        return $this->hasCompletedProfile();
    }
}
