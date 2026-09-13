<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;

/**
 * Personal learning surfaces: flashcards today, study plans next.
 *
 * Everything here is private to one user and therefore deliberately NOT indexable — there
 * is nothing here for a search engine, and a noindex page that leaks into results would
 * dilute the exam hubs that carry the SEO weight.
 */
class LearningController extends Controller
{
    public function flashcards(): View
    {
        return view('learning.flashcards', [
            'seo' => SeoBuilder::forRoute(
                'flashcards',
                __('Flashcards'),
                __('Spaced repetition built from your own wrong answers.'),
            ),
        ]);
    }
}
