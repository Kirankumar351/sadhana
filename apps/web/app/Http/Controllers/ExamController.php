<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Support\Locale;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Exam hub pages — the SEO engine.
 *
 * Cached aggressively for six hours and purged by an observer on save. These pages change
 * a few times a month and are read constantly, which is exactly the shape that justifies a
 * long TTL.
 */
class ExamController extends Controller
{
    public function index(): View
    {
        $categories = Cache::remember(
            Locale::cacheKey('exams:index'),
            now()->addHours(6),
            static fn () => ExamCategory::query()
                ->with(['exams' => fn ($q) => $q->active()->orderByDesc('view_count')])
                ->orderBy('sort_order')
                ->get(),
        );

        return view('exams.index', [
            'seo' => SeoBuilder::forRoute(
                'exams.index',
                __('All government exams'),
                __('Syllabus, exam pattern, previous papers and cutoffs for every major government exam, in Telugu.'),
            ),
            'categories' => $categories,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $exam = Cache::remember(
            Locale::cacheKey("exam:{$slug}"),
            now()->addHours(6),
            static fn () => Exam::query()
                ->where('slug', $slug)
                ->with([
                    'category',
                    'cutoffs' => fn ($q) => $q->orderByDesc('year')->limit(25),
                    'notifications' => fn ($q) => $q->published()->latest('published_at')->limit(5),
                    'materials' => fn ($q) => $q->where('status', 'published')->orderByDesc('download_count')->limit(10),
                ])
                ->firstOrFail(),
        );

        dispatch(function () use ($exam): void {
            $exam->incrementQuietly('view_count');
        })->afterResponse();

        $faq = is_array($exam->faq) ? $exam->faq : [];

        return view('exams.show', [
            'exam' => $exam,
            'seo' => SeoBuilder::forExam($exam),
            'schema' => SeoBuilder::courseSchema($exam),
            'faqSchema' => SeoBuilder::faqSchema($faq),
            'faq' => $faq,
        ]);
    }
}
