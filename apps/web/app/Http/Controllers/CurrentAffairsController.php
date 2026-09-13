<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Support\SeoBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * The daily current affairs digest.
 *
 * Integration Map gap 6: the pipeline produced a digest with nowhere to publish it.
 *
 * This is also the most SEO-valuable page type the product can have — daily fresh Telugu
 * content on high-volume queries, with a permanent URL per date. An exam hub page changes
 * a few times a month; this changes every morning, which is exactly what Google rewards
 * for a news-adjacent query.
 */
class CurrentAffairsController extends Controller
{
    public function index(): View
    {
        return $this->renderDate(today()->toDateString(), latest: true);
    }

    public function show(string $locale, string $date): View
    {
        return $this->renderDate($date);
    }

    private function renderDate(string $date, bool $latest = false): View
    {
        $items = NewsItem::query()
            ->where('status', 'published')
            ->whereDate('digest_date', $date)
            ->orderByRaw("CASE probability WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->with('sources')
            ->get();

        // Fall back to the most recent published digest rather than an empty page. Nobody
        // arriving at 6 AM before publishing should see nothing.
        if ($items->isEmpty() && $latest) {
            $mostRecent = NewsItem::query()->where('status', 'published')->max('digest_date');

            if ($mostRecent !== null) {
                $date = $mostRecent;
                $items = NewsItem::query()
                    ->where('status', 'published')
                    ->whereDate('digest_date', $date)
                    ->with('sources')
                    ->get();
            }
        }

        $readable = CarbonImmutable::parse($date)->format('d F Y');

        return view('current-affairs.index', [
            'seo' => SeoBuilder::forRoute(
                'current-affairs.index',
                __('Current affairs — :date', ['date' => $readable]),
                __('Yesterday\'s news filtered to what government exams actually ask, in Telugu, with the questions it is likely to become.'),
            ),
            'items' => $items,
            'date' => $date,
            'readableDate' => $readable,
            'archive' => NewsItem::query()
                ->where('status', 'published')
                ->select('digest_date')
                ->distinct()
                ->orderByDesc('digest_date')
                ->limit(30)
                ->pluck('digest_date'),
        ]);
    }
}
