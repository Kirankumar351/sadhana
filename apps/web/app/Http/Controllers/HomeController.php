<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailyQuiz;
use App\Models\Exam;
use App\Services\FeedService;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;

/**
 * The homepage.
 *
 * The promise above the fold is "find out if you actually qualify" — not a feature list.
 * Eligibility is the one thing no competitor does, so it is the first thing a visitor
 * sees, with a 30-second form that works before signing up.
 */
class HomeController extends Controller
{
    public function index(FeedService $feed): View
    {
        return view('home', [
            'seo' => SeoBuilder::forRoute(
                'home',
                __('Government job notifications in Telugu'),
                __('Every government and private job notification for Telangana and Andhra Pradesh, in Telugu, checked against your own age, category and qualification.'),
            ),
            'stats' => $feed->headlineStats(),
            'latest' => $feed->forUser(auth()->user(), [], 4),
            'popularExams' => Exam::query()->active()->orderByDesc('view_count')->limit(6)->get(),
            'quiz' => DailyQuiz::today(),
        ]);
    }
}
