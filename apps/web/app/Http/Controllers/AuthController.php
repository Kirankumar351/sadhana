<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Phone OTP authentication.
 *
 * No email, no password. The audience is on a cheap Android with a phone number they know
 * and an email address they do not check. Vol 2 Chapter 4 makes `password` nullable for
 * exactly this reason.
 *
 * THE OTP IS NEVER RETURNED TO THE CLIENT. It is stored server-side against the phone
 * number and compared there. In local development it is written to the log so the flow can
 * be exercised without an SMS provider; that branch is guarded by `app()->isLocal()` and
 * must never be reachable in production.
 */
class AuthController extends Controller
{
    private const OTP_TTL_SECONDS = 300;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function showLogin(): View
    {
        return view('auth.login', [
            'seo' => SeoBuilder::forRoute('login', __('Sign in'), __('Sign in with your phone number.')),
        ]);
    }

    /**
     * Send an OTP.
     *
     * Rate limited hard: 5 per minute per phone AND per IP. Vol 2 Chapter 15 sets this
     * because OTP endpoints are the cheapest thing to abuse in a product like this — each
     * request costs us real money at the SMS gateway.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'digits:10', 'regex:/^[6-9][0-9]{9}$/'],
        ], [
            'phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'phone.digits' => __('Enter all 10 digits.'),
        ]);

        $phone = $validated['phone'];

        foreach (["otp:phone:{$phone}", 'otp:ip:'.$request->ip()] as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages([
                    'phone' => __('Too many attempts. Try again in :seconds seconds.', [
                        'seconds' => RateLimiter::availableIn($key),
                    ]),
                ]);
            }

            RateLimiter::hit($key, 60);
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put("otp:{$phone}", [
            'code' => hash('sha256', $otp),
            'attempts' => 0,
        ], self::OTP_TTL_SECONDS);

        $this->deliver($phone, $otp);

        return redirect()
            ->route('otp.form', ['phone' => $phone])
            ->with('status', __('We sent a code to :phone', ['phone' => $phone]));
    }

    public function showOtpForm(string $locale, string $phone): View
    {
        return view('auth.verify', [
            'seo' => SeoBuilder::forRoute('login', __('Enter the code'), __('Enter the code we sent you.')),
            'phone' => $phone,
        ]);
    }

    /**
     * Verify and sign in, creating the account on first use.
     *
     * There is no separate registration flow on purpose: a signup form is a screen where
     * people leave, and everything it would ask for is either collected later (the profile)
     * or not needed at all.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'digits:10'],
            'code' => ['required', 'digits:6'],
        ]);

        $phone = $validated['phone'];
        $record = Cache::get("otp:{$phone}");

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => __('That code has expired. Ask for a new one.'),
            ]);
        }

        // Brute force costs one guess per attempt and the whole code dies after five.
        if ($record['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget("otp:{$phone}");

            throw ValidationException::withMessages([
                'code' => __('Too many wrong attempts. Ask for a new code.'),
            ]);
        }

        if (! hash_equals($record['code'], hash('sha256', $validated['code']))) {
            $record['attempts']++;
            Cache::put("otp:{$phone}", $record, self::OTP_TTL_SECONDS);

            throw ValidationException::withMessages([
                'code' => __('That code is not right.'),
            ]);
        }

        Cache::forget("otp:{$phone}");

        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => __('Aspirant'),
                'preferred_locale' => app()->getLocale(),
                'phone_verified_at' => now(),
            ],
        );

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        if ($user->is_banned) {
            throw ValidationException::withMessages([
                'phone' => __('This account has been suspended.'),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $user->forceFill(['last_active_at' => now()])->saveQuietly();

        // A user with no date of birth cannot be given an eligibility answer, so the
        // profile comes first. Everything after it is skippable.
        return $user->hasCompletedProfile()
            ? redirect()->route('dashboard')
            : redirect()->route('profile.edit')->with('status', __('One last step — tell us who you are.'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Deliver the code.
     *
     * In local development it goes to the log so the flow is testable with no SMS
     * provider. In any other environment this must call the real gateway, and the guard
     * below is what stops a misconfigured staging box from silently logging OTPs.
     */
    private function deliver(string $phone, string $otp): void
    {
        if (app()->isLocal()) {
            Log::info("OTP for {$phone}: {$otp}");

            return;
        }

        // TODO: WhatsApp Cloud API first (highest open rate in this market), SMS fallback.
        Log::warning('OTP delivery is not configured for this environment.', ['phone' => $phone]);
    }
}
