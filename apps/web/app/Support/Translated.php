<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reading a JSON translation column in a view.
 *
 * Two reasons this exists rather than `$model->field[$locale] ?? reset($model->field)`:
 *
 *   1. `reset()` takes its argument by reference, and an Eloquent attribute is a magic
 *      property — so that expression throws "indirect modification of overloaded property"
 *      the moment the locale key is missing, which is exactly the half-translated case the
 *      fallback was written for. It fails only when it is needed.
 *
 *   2. The fallback order is a product decision, not an accident of array order: the
 *      requested locale, then the configured default, then whatever exists. A student
 *      reading in Telugu should see Telugu where we have it and English rather than nothing
 *      where we do not.
 */
final class Translated
{
    /**
     * @param  array<string, mixed>|string|null  $value
     */
    public static function from(array|string|null $value, ?string $locale = null, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        $locale ??= app()->getLocale();

        if (isset($value[$locale]) && filled($value[$locale])) {
            return (string) $value[$locale];
        }

        $fallback = (string) config('locales.default');

        if (isset($value[$fallback]) && filled($value[$fallback])) {
            return (string) $value[$fallback];
        }

        foreach ($value as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return $default;
    }
}
