<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'setlocale' => SetLocale::class,
        ]);

        /**
         * Every route is locale-prefixed, so the sign-in redirect needs one.
         *
         * This is set explicitly rather than relying on the URL defaults that SetLocale
         * registers, because Laravel's middleware priority can resolve Authenticate before
         * a custom alias in the same group — and then route('login') has no locale and
         * throws instead of redirecting.
         *
         * Reading it off the route (with the configured default as a fallback) makes the
         * redirect correct regardless of ordering.
         */
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login', [
            'locale' => $request->route('locale') ?? config('locales.default'),
        ]));

        $middleware->redirectUsersTo(fn (Request $request): string => route('dashboard', [
            'locale' => $request->route('locale') ?? config('locales.default'),
        ]));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
