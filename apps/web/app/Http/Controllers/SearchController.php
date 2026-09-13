<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamNotification;
use App\Models\Material;
use App\Models\Post;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Search — Web Portal screen 06.
 *
 * Searches across notifications, exams, material and answered doubts in one place, because
 * a student searching "group 2 syllabus" does not know or care which of our content types
 * holds the answer.
 *
 * WHY THIS IS A DATABASE SEARCH AND NOT MEILISEARCH, FOR NOW. Meilisearch gives typo
 * tolerance and per-locale tokenisation, which matter enormously for Telugu — but it is a
 * service that can be down, and a search box that 500s is worse than one that is merely
 * less clever. This implementation is the fallback path that must always work; Scout is
 * layered on top when the service is reachable.
 *
 * Results are grouped by type rather than interleaved by relevance score. A blended list
 * looks smarter and is harder to scan: someone looking for a syllabus wants to see the
 * exam pages together, not one exam page between two notifications.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim($request->string('q')->toString());

        return view('search.index', [
            'seo' => SeoBuilder::forRoute(
                'search',
                $query !== '' ? __('Search: :q', ['q' => $query]) : __('Search'),
                __('Search every notification, exam, syllabus and answered doubt.'),
            ),
            'query' => $query,
            'notifications' => $query === '' ? collect() : $this->notifications($query),
            'exams' => $query === '' ? collect() : $this->exams($query),
            'materials' => $query === '' ? collect() : $this->materials($query),
            'doubts' => $query === '' ? collect() : $this->doubts($query),
        ]);
    }

    /**
     * @return Collection<int, ExamNotification>
     */
    private function notifications(string $query): Collection
    {
        return ExamNotification::query()
            ->published()
            ->where(fn ($q) => $this->matchTranslatable($q, 'title', $query)
                ->orWhere('organisation', 'like', "%{$query}%")
                ->orWhere('slug', 'like', '%'.str_replace(' ', '-', strtolower($query)).'%'))
            // Open jobs first. An expired notification still answers "did I miss it?" but
            // it is never what someone searching today is hoping to find.
            ->orderByRaw('CASE WHEN apply_end_date IS NULL OR apply_end_date >= ? THEN 0 ELSE 1 END', [today()])
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, Exam>
     */
    private function exams(string $query): Collection
    {
        return Exam::query()
            ->active()
            ->where(fn ($q) => $this->matchTranslatable($q, 'name', $query)
                ->orWhere('short_name', 'like', "%{$query}%")
                ->orWhere('conducting_body', 'like', "%{$query}%")
                ->orWhere('slug', 'like', '%'.str_replace(' ', '-', strtolower($query)).'%'))
            ->orderByDesc('view_count')
            ->limit(6)
            ->get();
    }

    /**
     * @return Collection<int, Material>
     */
    private function materials(string $query): Collection
    {
        return Material::query()
            ->where('status', 'published')
            ->where(fn ($q) => $this->matchTranslatable($q, 'title', $query)
                ->orWhere('subject', 'like', "%{$query}%"))
            ->orderByDesc('download_count')
            ->limit(6)
            ->get();
    }

    /**
     * Answered doubts only.
     *
     * An unanswered question in search results is a dead end — the reader came looking for
     * an answer, and showing them someone else with the same unanswered question helps
     * nobody. This is also the archive that compounds: each answered doubt keeps earning
     * its place in search for years.
     *
     * @return Collection<int, Post>
     */
    private function doubts(string $query): Collection
    {
        return Post::query()
            ->where('status', 'published')
            ->where('answer_count', '>', 0)
            ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")
                ->orWhere('body', 'like', "%{$query}%"))
            ->orderByDesc('upvotes')
            ->limit(6)
            ->get();
    }

    /**
     * Match a spatie/laravel-translatable JSON column in the current locale AND the
     * fallback.
     *
     * Searching only the active locale would hide an English-titled notification from a
     * Telugu user — and during the first months most content arrives in English first,
     * so that would hide most of the library from most of our users.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    private function matchTranslatable($builder, string $column, string $query)
    {
        foreach (array_unique([app()->getLocale(), (string) config('locales.fallback')]) as $locale) {
            $builder->orWhere("{$column}->{$locale}", 'like', "%{$query}%");
        }

        return $builder;
    }
}
