<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeneratedNote;
use App\Support\SeoBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
            'seo' => SeoBuilder::private(
                __('Flashcards'),
                __('Spaced repetition built from your own wrong answers.'),
            ),
        ]);
    }

    public function notes(): View
    {
        return view('learning.notes', [
            'seo' => SeoBuilder::private(
                __('Notes generator'),
                __('Structured notes on any syllabus topic, built from our own material.'),
            ),
        ]);
    }

    public function showNote(string $locale, GeneratedNote $note): View
    {
        abort_unless($note->user_id === auth()->id(), 404);

        return view('learning.note', [
            'note' => $note,
            'seo' => SeoBuilder::private($note->title),
        ]);
    }

    /**
     * Delete a saved note.
     *
     * Private content the student generated: theirs to remove, immediately and completely.
     * Nothing here is anonymised and kept the way a community post is, because nobody else
     * has ever seen it.
     */
    public function destroyNote(string $locale, GeneratedNote $note): RedirectResponse
    {
        abort_unless($note->user_id === auth()->id(), 404);

        $note->delete();

        return redirect()
            ->route('notes.index', ['locale' => app()->getLocale()])
            ->with('status', __('Note deleted.'));
    }

    public function studyPlan(): View
    {
        return view('learning.study-plan', [
            'seo' => SeoBuilder::private(
                __('Study plan'),
                __('Your remaining days, allocated across the topics where they are worth the most.'),
            ),
        ]);
    }
}
