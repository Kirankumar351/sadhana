<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\TestResult;
use App\Models\TestSeries;
use App\Services\Billing\FeatureGate;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Test series.
 *
 * The free tier is not a crippled demo. One full-length mock per series is free forever,
 * plus the daily quiz — a student can prepare seriously without paying. What is paid is
 * the rest of the series and the deep analytics.
 *
 * The first mock being free is not generosity, it is how the sale works: people buy what
 * they have tried. Gating the sample loses the sale and the trust together.
 */
class TestSeriesController extends Controller
{
    public function index(): View
    {
        return view('tests.index', [
            'seo' => SeoBuilder::forRoute(
                'tests.index',
                __('Mock tests'),
                __('Full-length mock tests matching the real exam pattern and timing, with topic-wise analysis.'),
            ),
            'series' => TestSeries::query()
                ->where('status', 'published')
                ->with(['exam:id,slug,name', 'tests' => fn ($q) => $q->where('status', 'published')->orderBy('sequence')])
                ->get(),
        ]);
    }

    public function show(string $locale, string $slug, FeatureGate $gate): View
    {
        $series = TestSeries::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['exam', 'tests' => fn ($q) => $q->where('status', 'published')->orderBy('sequence')])
            ->firstOrFail();

        $user = auth()->user();

        return view('tests.show', [
            'series' => $series,
            'seo' => SeoBuilder::forRoute(
                'tests.show',
                (string) $series->title,
                strip_tags((string) $series->description),
                ['slug' => $series->slug],
            ),
            // Asked as an entitlement, never as "is this user premium". Five different
            // things can grant it and feature code should know about none of them.
            'hasFullAccess' => $series->is_free || $gate->allows($user, 'test_series_full'),
            'attempts' => $user !== null
                ? TestResult::query()->where('user_id', $user->id)
                    ->whereIn('test_id', $series->tests->pluck('id'))
                    ->get()->keyBy('test_id')
                : collect(),
        ]);
    }

    public function result(Request $request, string $locale, TestResult $result): View
    {
        abort_unless($result->user_id === $request->user()->id, 403);

        return view('tests.result', [
            'seo' => SeoBuilder::forRoute('tests.index', __('Your result'), __('Your mock test analysis.')),
            'result' => $result->load('test.series'),
        ]);
    }
}
