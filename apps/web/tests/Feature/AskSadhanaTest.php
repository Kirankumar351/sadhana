<?php

declare(strict_types=1);

use App\Livewire\Ai\AskSadhana;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\FakeAi;

/**
 * The Ask Sadhana box, through a full Livewire request cycle.
 *
 * Reported from the live page: a typed question came back with "The question field is
 * required". Each call below is a real dehydrate/hydrate round trip, which is where a
 * question can be lost between the browser and the component.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('submits the question that was typed', function (): void {
    FakeAi::install('SSC CGL Tier 1 has four sections.', [
        ['content' => 'SSC Combined Graduate Level 2026 Tier 1 has four sections of 25 questions each.', 'title' => 'SSC CGL 2026', 'locale' => 'en'],
        ['content' => 'SSC CGL Tier 1 is a computer based examination of 60 minutes.', 'title' => 'SSC CGL 2026', 'locale' => 'en'],
    ]);

    Livewire::test(AskSadhana::class)
        ->set('question', 'hi i want the ssc exam')
        ->call('ask')
        ->assertHasNoErrors()
        ->assertSet('asked', true)
        ->assertSee('SSC CGL Tier 1 has four sections.');
});

it('takes a new question after clearing the last one', function (): void {
    FakeAi::install('An answer.', [
        ['content' => 'First passage about the SSC exam.', 'locale' => 'en'],
        ['content' => 'Second passage about the SSC exam.', 'locale' => 'en'],
    ]);

    Livewire::test(AskSadhana::class)
        ->set('question', 'hi i want the ssc exam')
        ->call('ask')
        ->call('reset_')
        ->assertSet('question', '')
        ->set('question', 'What is the SSC CGL exam pattern?')
        ->call('ask')
        ->assertHasNoErrors()
        ->assertSet('asked', true);
});

it('still asks for a question when nothing was typed', function (): void {
    Livewire::test(AskSadhana::class)
        ->set('question', '')
        ->call('ask')
        ->assertHasErrors(['question' => 'required']);
});

it('degrades to a designed state, never an error, when the AI layer is unreachable', function (): void {
    // No fakes: no provider key in tests, so retrieval cannot run. The page must still work.
    Livewire::test(AskSadhana::class)
        ->set('question', 'hi i want the ssc exam')
        ->call('ask')
        ->assertHasNoErrors()
        ->assertSet('asked', true)
        // Through __(): the page renders in Telugu by default, so the English source text is
        // not what appears.
        ->assertSee(__('The assistant is not available right now.'));
});
