<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Services\Privacy\DataExportService;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Privacy and data rights.
 *
 * DPDP Act 2023 requires both of these to work, not to exist as a policy sentence. They
 * are built in v1 rather than retrofitted, and the deletion actually deletes.
 */
class PrivacyController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.privacy', [
            'seo' => SeoBuilder::forRoute('privacy', __('Your data'), __('See what we hold, download it, or delete your account.')),
            'consents' => $request->user()->load('profile'),
            'requests' => DataRequest::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Download everything, immediately.
     *
     * Streamed as a direct JSON download rather than queued behind an email link. A
     * 30-day statutory window is the legal maximum, not a target, and this audience does
     * not reliably check email — a link they never open is a right they never exercised.
     */
    public function export(Request $request, DataExportService $service): JsonResponse
    {
        DataRequest::create([
            'user_id' => $request->user()->id,
            'type' => 'export',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()
            ->json($service->export($request->user()), 200, [
                'Content-Disposition' => 'attachment; filename="sadhana-my-data.json"',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Delete the account.
     *
     * Requires the user to type their own phone number. A single confirm button on an
     * irreversible action is how people delete three years of preparation history by
     * accident.
     */
    public function destroy(Request $request, DataExportService $service): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'confirm_phone' => ['required', 'string'],
        ]);

        if ($request->string('confirm_phone')->toString() !== $user->phone) {
            return back()->withErrors([
                'confirm_phone' => __('That does not match your phone number. Nothing has been deleted.'),
            ]);
        }

        DataRequest::create([
            'user_id' => $user->id,
            'type' => 'deletion',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $service->delete($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Your account and data have been deleted.'));
    }
}
