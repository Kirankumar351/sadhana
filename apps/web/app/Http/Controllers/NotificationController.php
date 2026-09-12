<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExamNotification;
use App\Services\EligibilityService;
use App\Services\FeedService;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, FeedService $feed): View
    {
        $filters = $request->only([
            'job_type', 'qualification', 'district', 'state', 'closing_soon', 'exam_id',
        ]);

        return view('notifications.index', [
            'seo' => SeoBuilder::forRoute(
                'notifications.index',
                __('Latest government job notifications'),
                __('Every open government and private job notification, with an eligibility check against your own profile.'),
            ),
            'notifications' => $feed->forUser($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    public function show(string $locale, string $slug, EligibilityService $eligibility): View
    {
        $notification = ExamNotification::query()
            ->published()
            ->where('slug', $slug)
            ->with(['exam'])
            ->firstOrFail();

        // Fire-and-forget view counter. Never block the response on analytics, least of
        // all on the page that takes 50x traffic on notification day.
        dispatch(function () use ($notification): void {
            $notification->incrementQuietly('view_count');
        })->afterResponse();

        return view('notifications.show', [
            'n' => $notification,
            'eligibility' => $eligibility->check($notification, auth()->user()?->profile),
            'seo' => SeoBuilder::forNotification($notification),
            'schema' => SeoBuilder::jobPostingSchema($notification),
        ]);
    }

    public function saved(Request $request, FeedService $feed): View
    {
        $saved = $request->user()->savedNotifications()
            ->published()
            ->with('exam:id,slug,name')
            ->orderByDesc('apply_end_date')
            ->paginate(20);

        return view('notifications.saved', [
            'seo' => SeoBuilder::forRoute('saved', __('Saved jobs'), __('Jobs you saved.')),
            'notifications' => $feed->decorate($saved, $request->user()),
        ]);
    }
}
