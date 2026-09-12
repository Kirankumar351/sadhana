<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailyQuiz;
use App\Services\FeedService;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, FeedService $feed): View
    {
        $user = $request->user();

        return view('dashboard', [
            'seo' => SeoBuilder::forRoute('dashboard', __('Dashboard'), __('Your exams, your streak, your feed.')),
            'user' => $user,
            'streak' => $user->streak,
            'quiz' => DailyQuiz::today(),
            'attempt' => DailyQuiz::today()?->attemptFor($user),
            'feed' => $feed->forUser($user, [], 5),
            'exams' => $user->examPreferences()->get(),
            'savedCount' => $user->savedNotifications()->count(),
        ]);
    }
}
