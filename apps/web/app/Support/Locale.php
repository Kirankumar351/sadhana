<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * Locale helpers.
 *
 * The forgotten-locale cache key is the most common i18n bug there is, and here it is
 * also one of the most damaging: a Telugu user served a cached English exam page sees a
 * broken product, and a cached Telugu page served to an English user is worse.
 *
 * Never call Cache::remember() with a raw string key anywhere in this codebase.
 * Always Locale::cacheKey(). This is checked in CI.
 */
final class Locale
{
    public static function current(): string
    {
        return App::getLocale();
    }

    public static function fallback(): string
    {
        return (string) config('locales.fallback', 'en');
    }

    /**
     * Namespace a cache key with the active locale.
     *
     *   Cache::remember(Locale::cacheKey("exam:{$slug}"), $ttl, $fn);
     */
    public static function cacheKey(string $key): string
    {
        return $key.':'.self::current();
    }

    /**
     * Every active locale's variant of a key — used when invalidating after a content edit.
     * Forgetting one locale here is how a stale translation outlives the correction.
     *
     * @return list<string>
     */
    public static function allCacheKeys(string $key): array
    {
        return array_map(
            static fn (string $locale): string => $key.':'.$locale,
            self::active(),
        );
    }

    /**
     * @return list<string>
     */
    public static function active(): array
    {
        /** @var array<string, array{active: bool}> $supported */
        $supported = config('locales.supported', []);

        return array_values(array_keys(array_filter(
            $supported,
            static fn (array $config): bool => (bool) ($config['active'] ?? false),
        )));
    }

    public static function isActive(string $locale): bool
    {
        return in_array($locale, self::active(), true);
    }

    public static function native(string $locale): string
    {
        return (string) config("locales.supported.{$locale}.native", $locale);
    }

    /**
     * The routing constraint, e.g. "te|en". Kept here so adding a language stays a config
     * change rather than an edit to routes/web.php.
     */
    public static function routePattern(): string
    {
        return implode('|', self::active());
    }
}
