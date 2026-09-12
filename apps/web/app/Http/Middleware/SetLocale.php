<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the locale from the URL and makes it ambient for the rest of the request.
 *
 * `URL::defaults(['locale' => $locale])` is the line that matters most: every route()
 * call thereafter inherits the current locale automatically. Without it, every link in
 * every view has to pass the locale by hand, and the one that gets forgotten silently
 * throws a Telugu user onto an English page.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        if (! Locale::isActive($locale)) {
            $locale = (string) config('locales.default');
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        Cookie::queue('locale', $locale, 60 * 24 * 365);

        // Keep the signed-in user's stored preference in step with what they are actually
        // reading. updateQuietly so this does not fire model events on every page view.
        $user = $request->user();

        if ($user !== null && $user->preferred_locale !== $locale) {
            $user->updateQuietly(['preferred_locale' => $locale]);
        }

        return $next($request);
    }
}
