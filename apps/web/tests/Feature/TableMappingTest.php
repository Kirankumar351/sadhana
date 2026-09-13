<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Every model points at a table that exists.
 *
 * THIS TEST EXISTS BECAUSE OF AiCache. The table is ai_cache; Eloquent pluralised the class
 * name to ai_caches and every response-cache lookup threw. The gateway catches Throwable
 * and degrades to unavailable, exactly as designed, so the entire AI layer returned
 * "temporarily unavailable" in perfect silence with no error anywhere a person would look.
 *
 * Irregular table names are not rare here: ai_cache, glossary, analytics_daily, test_series.
 * A convention that guesses silently needs a test, not a habit.
 */
it('maps every model to a table that exists', function (): void {
    $missing = [];

    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $model = new $class;

        if (! $model instanceof Model) {
            continue;
        }

        if (! Schema::hasTable($model->getTable())) {
            $missing[] = class_basename($class).' uses '.$model->getTable();
        }
    }

    expect($missing)->toBe([]);
});
