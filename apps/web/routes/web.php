<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Support\Locale;
use Illuminate\Support\Facades\Route;

/**
 * URL structure: /te/... and /en/... as subdirectories.
 *
 * Never a query parameter, never a subdomain. Subdirectories with hreflang are what Google
 * documents for multilingual sites, and they keep the domain's authority in one place
 * instead of splitting it across hosts.
 *
 * Slugs are identical across locales, so /te/exams/tgpsc-group-2 and
 * /en/exams/tgpsc-group-2 are the same page in two languages. Translating the slug would
 * fragment backlinks and double the sitemap for no gain.
 */

// Bare "/" resolves by cookie, then by browser preference, then to Telugu.
Route::get('/', function () {
    $cookie = request()->cookie('locale');

    $locale = Locale::isActive((string) $cookie)
        ? (string) $cookie
        : (request()->getPreferredLanguage(Locale::active()) ?: config('locales.default'));

    return redirect("/{$locale}");
});

Route::group([
    'prefix' => '{locale}',
    'where' => ['locale' => Locale::routePattern()],
    'middleware' => ['setlocale'],
], function () {

    Route::get('/', [HomeController::class, 'index'])->name('home');

    // ---- auth: phone OTP, no password ----
    Route::middleware('guest')->group(function () {
        Route::get('/sign-in', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/sign-in', [AuthController::class, 'sendOtp'])->name('otp.send');
        Route::get('/verify/{phone}', [AuthController::class, 'showOtpForm'])
            ->whereNumber('phone')->name('otp.form');
        Route::post('/verify', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    });

    Route::post('/sign-out', [AuthController::class, 'logout'])
        ->middleware('auth')->name('logout');

    // ---- notifications: the reason people find us ----
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{slug}', [NotificationController::class, 'show'])->name('notifications.show');

    // ---- exam hub: the SEO engine ----
    Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('/exams/{slug}', [ExamController::class, 'show'])->name('exams.show');

    // ---- the daily habit ----
    Route::get('/quiz', [QuizController::class, 'today'])->name('quiz.today');
    Route::get('/quiz/result/{attempt}', [QuizController::class, 'result'])->name('quiz.result');
    Route::get('/leaderboard', [QuizController::class, 'leaderboard'])->name('quiz.leaderboard');

    // ---- signed in ----
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/saved', [NotificationController::class, 'saved'])->name('saved');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});
