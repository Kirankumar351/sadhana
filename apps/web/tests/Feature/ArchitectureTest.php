<?php

declare(strict_types=1);

/**
 * Architecture rules that are cheaper to enforce than to remember.
 */

/**
 * NOTHING OUTSIDE THE GATEWAY MAY TALK TO A MODEL PROVIDER.
 *
 * Cost caps, refusal logging, prompt versioning, content caching and output filtering all
 * live in AiGateway. The moment a feature can call the API directly, every one of those
 * guarantees becomes optional — and the one that gets skipped will be the one that
 * mattered. This is the rule most likely to be broken by someone in a hurry.
 */
arch('only the AI layer talks to the model provider')
    ->expect('App')
    ->not->toUse(['Anthropic', 'OpenAI'])
    ->ignoring('App\Services\AI');

/**
 * Business logic lives in services, not in controllers or Livewire components.
 * Controllers orchestrate; services decide.
 */
arch('controllers do not query the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('models do not dispatch HTTP requests')
    ->expect('App\Models')
    ->not->toUse(['Illuminate\Support\Facades\Http']);

/**
 * Strict types everywhere. A silent string-to-int coercion in a fee or an age is exactly
 * the class of bug this product cannot afford.
 */
arch('everything declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no leftover debugging')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();
