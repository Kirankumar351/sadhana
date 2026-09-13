<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * A fallback locale for route() before any route has matched.
         *
         * SetLocale sets the real one, but it is ROUTE middleware — it never runs for a URL
         * that matches no route. The 404 view then calls route('notifications.index'), finds
         * no locale to fill {locale} with, and throws. The result was that every mistyped
         * URL and every bot probe returned 500 instead of 404.
         *
         * That is worse than an ugly error page: Google treats a 500 as "come back later"
         * and a 404 as "this is gone". Serving 500s for dead URLs on an SEO-driven product
         * is the expensive kind of broken.
         *
         * SetLocale overwrites this the moment a locale route matches, so the real locale
         * still wins everywhere it exists.
         */
        URL::defaults(['locale' => config('locales.default')]);
    }
}
