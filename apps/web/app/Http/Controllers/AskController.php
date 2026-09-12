<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;

class AskController extends Controller
{
    public function index(): View
    {
        return view('ai.ask', [
            'seo' => SeoBuilder::forRoute(
                'ask',
                __('Ask Sadhana'),
                __('Ask any question about a government exam, syllabus or notification and get an answer from verified material, with sources.'),
            ),
        ]);
    }
}
