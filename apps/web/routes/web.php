<?php

declare(strict_types=1);

use App\Http\Controllers\AskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MaterialController;
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

/**
 * The offline fallback, outside the locale group so the service worker can cache one
 * copy rather than one per language. It is the only page in the product that must be
 * reachable with no network at all.
 */
Route::view('/offline', 'offline')->name('offline');

/**
 * Gateway webhook. Outside the locale group and outside auth deliberately - it is a
 * server-to-server call authenticated by signature, and a payment gateway has no
 * session and no language.
 */
Route::post('/webhooks/razorpay', [BillingController::class, 'webhook'])
    ->name('webhooks.razorpay');

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

    // ---- Ask Sadhana ----
    //
    // Its own route AND a persistent entry point beside search, because a feature nobody
    // can find is a feature nobody uses. Ask sits next to search deliberately: they are
    // the same intent -- "I have a question" -- expressed two ways.
    Route::get('/ask', [AskController::class, 'index'])->name('ask');

    // ---- the daily habit ----
    Route::get('/quiz', [QuizController::class, 'today'])->name('quiz.today');
    Route::get('/quiz/result/{attempt}', [QuizController::class, 'result'])->name('quiz.result');
    Route::get('/leaderboard', [QuizController::class, 'leaderboard'])->name('quiz.leaderboard');

    // ---- material library ----
    //
    // Downloads go through the application, never a direct object URL. A taken-down file
    // must stop being reachable immediately, and an unlisted R2 URL that leaks is public
    // forever.
    Route::get('/material', [MaterialController::class, 'index'])->name('material.index');
    Route::get('/material/{slug}', [MaterialController::class, 'show'])->name('material.show');
    Route::get('/material/{slug}/download', [MaterialController::class, 'download'])->name('material.download');

    // ---- doubt community ----
    //
    // Decision D8: this does not open until 5,000+ DAU, seeded with real answered
    // questions. An empty forum signals a dead product.
    Route::get('/doubts', [CommunityController::class, 'index'])->name('community.index');
    Route::get('/doubts/{slug}', [CommunityController::class, 'show'])->name('community.show');

    // ---- money ----
    Route::get('/premium', [BillingController::class, 'plans'])->name('billing.plans');

    // ---- signed in ----
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/saved', [NotificationController::class, 'saved'])->name('saved');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::post('/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('/checkout/callback', [BillingController::class, 'callback'])->name('billing.callback');

        // Contributing requires an account, so an upload and a doubt can be attributed
        // and, where necessary, acted on.
        Route::get('/doubts/ask/new', [CommunityController::class, 'create'])->name('community.create');
        Route::get('/material/share/new', [MaterialController::class, 'create'])->name('material.create');
    });
});
