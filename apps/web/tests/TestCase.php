<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every route in this application lives under a {locale} prefix, and SetLocale fills
     * that parameter in as a URL default on every real request — which is why views call
     * route('exams.index') with no locale and it works.
     *
     * Livewire::test() builds a component without going through the middleware stack, so
     * without this line any view containing a route() call throws "missing parameter
     * locale" in a test and only in a test. Setting the same default here makes the test
     * environment match the one the code actually runs in.
     */
    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => config('locales.default')]);
    }
}
