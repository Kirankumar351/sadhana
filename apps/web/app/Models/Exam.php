<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

/**
 * An exam — one permanent page per exam, per language.
 *
 * This is the SEO engine: roughly 60% of Year 1 acquisition comes from these pages, and
 * Vol 1 is explicit that they should be treated as a product, not as content.
 *
 * The slug is NEVER translated. One slug across every locale, or backlinks fragment and
 * the ranking the whole business model depends on is split in half.
 *
 * Soft deletes are mandatory. Deleting an exam page loses its search position permanently,
 * and a three-year-old ranking cannot be rebuilt.
 */
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = [
        'name', 'description', 'eligibility_summary',
        'exam_pattern', 'syllabus', 'meta_title', 'meta_description', 'faq',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_interview' => 'boolean',
            'has_mains' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ---------------------------------------------------------------- relations

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'category_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ExamNotification::class);
    }

    public function cutoffs(): HasMany
    {
        return $this->hasMany(ExamCutoff::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_exam_preferences')
            ->withPivot(['priority', 'target_date', 'is_primary'])
            ->withTimestamps();
    }

    // ---------------------------------------------------------------- scopes

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Exams relevant to a state, including all-India ones. A `null` state means national,
     * not "no state" — an SSC notification matters to a Telangana aspirant too.
     */
    public function scopeForState(Builder $q, ?string $state): Builder
    {
        if ($state === null) {
            return $q;
        }

        return $q->where(fn (Builder $q) => $q->whereNull('state')->orWhere('state', $state));
    }

    // ---------------------------------------------------------------- helpers

    public function latestNotification(): ?ExamNotification
    {
        return $this->notifications()
            ->published()
            ->latest('published_at')
            ->first();
    }

    /**
     * The structured syllabus for the active locale, falling back to English.
     * Returns a list of sections so the exam page and the notes generator read the same
     * shape — the syllabus is an owner field and both must see it identically.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syllabusSections(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $syllabus = $this->getTranslation('syllabus', $locale, useFallbackLocale: true);

        if (is_string($syllabus)) {
            $syllabus = json_decode($syllabus, true);
        }

        return is_array($syllabus) ? $syllabus : [];
    }

    /**
     * Cutoffs grouped by year, newest first. We show real history and never a prediction —
     * a cutoff depends on vacancy count, paper difficulty and how many people sat the exam,
     * so a confident guess makes someone plan wrongly.
     *
     * @return array<int, Collection<int, ExamCutoff>>
     */
    public function cutoffsByYear(int $years = 5): array
    {
        return $this->cutoffs()
            ->orderByDesc('year')
            ->get()
            ->groupBy('year')
            ->take($years)
            ->all();
    }
}
