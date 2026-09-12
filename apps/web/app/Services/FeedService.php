<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the notification feed.
 *
 * The central design decision here is that we NEVER HIDE CONTENT — we label it.
 *
 * A user who fails only on age still wants to see the notification: they may hold a
 * relaxation we do not know about, or be reading it for a sibling. So preferred exams
 * float to the top and ineligible items sort down, but nothing is filtered out unless the
 * user explicitly asks for "eligible only".
 */
final class FeedService
{
    public function __construct(
        private readonly EligibilityService $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ExamNotification>
     */
    public function forUser(?User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ExamNotification::query()
            ->published()
            ->open()
            ->with(['exam:id,slug,name']);

        $this->applyFilters($query, $filters);

        // Followed exams first, everything else still visible below.
        if ($user !== null) {
            $ids = $user->examPreferences()->pluck('exams.id')->all();

            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $query->orderByRaw("CASE WHEN exam_id IN ({$placeholders}) THEN 0 ELSE 1 END", $ids);
            }
        }

        $paginator = $query
            ->orderByRaw('CASE WHEN apply_end_date IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return $this->decorate($paginator, $user);
    }

    /**
     * Attach the eligibility result to each row so the view renders a badge without
     * re-running the check per component.
     *
     * @param  LengthAwarePaginator<int, ExamNotification>  $paginator
     * @return LengthAwarePaginator<int, ExamNotification>
     */
    public function decorate(LengthAwarePaginator $paginator, ?User $user): LengthAwarePaginator
    {
        $profile = $user?->profile;

        $paginator->getCollection()->each(function (ExamNotification $n) use ($profile): void {
            $n->eligibility = $this->eligibility->check($n, $profile);
        });

        return $paginator;
    }

    /**
     * @param  Builder<ExamNotification>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (! empty($filters['qualification'])) {
            $query->where('min_qualification', $filters['qualification']);
        }

        if (! empty($filters['exam_id'])) {
            $query->where('exam_id', $filters['exam_id']);
        }

        /**
         * A notification with no district restriction applies everywhere, so a district
         * filter must keep the NULL rows. Filtering them out would hide every all-India
         * job from anyone who picked a district — which is most of the feed.
         */
        if (! empty($filters['district'])) {
            $query->where(fn ($q) => $q
                ->whereNull('allowed_districts')
                ->orWhereJsonContains('allowed_districts', $filters['district']));
        }

        if (! empty($filters['state'])) {
            $query->where(fn ($q) => $q
                ->whereNull('allowed_states')
                ->orWhereJsonContains('allowed_states', $filters['state']));
        }

        if (! empty($filters['closing_soon'])) {
            $query->closingWithin(7);
        }
    }

    /**
     * Counts for the homepage. Cached briefly — these appear on the highest-traffic page
     * in the product and must not become a query per visitor on notification day.
     *
     * @return array{open: int, vacancies: int}
     */
    public function headlineStats(): array
    {
        return cache()->remember('feed:stats', now()->addMinutes(10), static function (): array {
            $base = ExamNotification::query()->published()->open();

            return [
                'open' => (clone $base)->count(),
                'vacancies' => (int) (clone $base)->sum('total_vacancies'),
            ];
        });
    }
}
