<?php

declare(strict_types=1);

use App\Livewire\Ai\NotesGenerator as Screen;
use App\Models\AiChunk;
use App\Models\Exam;
use App\Models\GeneratedNote;
use App\Models\User;
use App\Services\AI\Features\NotesGenerator;
use Livewire\Livewire;
use Tests\Support\FakeAi;

/**
 * The notes generator.
 *
 * The rules worth testing are about what it refuses to do. Notes come from our own corpus
 * only, a thin corpus produces a refusal rather than padding, and a note the student saved
 * is a snapshot that cannot change under them afterwards.
 *
 * These run through the REAL gateway with fakes at the provider boundary, so the retrieval
 * gate, the output guard, the caps and the meter are all exercised rather than skipped.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['preferred_locale' => 'te']);
    $this->exam = Exam::factory()->create([
        'syllabus' => ['en' => [
            'Paper IV — Telangana Movement' => [
                'The idea of Telangana, 1948-1970',
                'Mobilisation phase, 1971-1990',
            ],
        ]],
    ]);
});

/**
 * Two passages is the retrieval floor, so two is what a working corpus means here.
 *
 * @param  list<array{content: string, title?: string}>  $corpus
 */
function corpus(array $corpus = []): array
{
    return $corpus !== [] ? $corpus : [
        ['content' => 'The Gentlemen agreement was signed in 1956.', 'title' => 'Paper IV syllabus'],
        ['content' => 'Telangana Praja Samithi was formed in 1969.', 'title' => 'Group 2 2024 Paper IV'],
    ];
}

it('offers only topics that are on the paper', function (): void {
    // Picked, not typed: a free-text box invites questions the corpus was never meant to
    // cover, and the refusal then reads as the product being broken.
    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('paper', 'Paper IV — Telangana Movement')
        ->assertSee('The idea of Telangana, 1948-1970')
        ->assertSee('Mobilisation phase, 1971-1990');
});

it('clears a chosen topic when the paper changes', function (): void {
    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('paper', 'Paper IV — Telangana Movement')
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->set('paper', 'Paper I')
        // A topic left over from another paper would generate against the wrong corpus.
        ->assertSet('topic', '');
});

it('renders generated notes with the sources they were built from', function (): void {
    FakeAi::install('తెలంగాణ ఆలోచన దశ', corpus());

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->assertSee('తెలంగాణ ఆలోచన దశ')
        ->assertSee('Paper IV syllabus')
        ->assertSee('Group 2 2024 Paper IV');
});

it('says so rather than padding when nothing is indexed', function (): void {
    // The honest failure is the feature. Confident notes assembled from nothing are the one
    // outcome worse than no notes.
    FakeAi::install('Notes', []);

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->assertSee(__('We do not hold enough material on this topic yet.'))
        ->assertDontSee(__('Save to my notes'));
});

it('refuses rather than answering from a single passage', function (): void {
    // One passage is not grounding, it is a quote with a paraphrase attached.
    FakeAi::install('Notes', [['content' => 'A single indexed line.', 'title' => 'Only source']]);

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->assertSee(__('We do not hold enough material on this topic yet.'));
});

it('strips a date the corpus does not support', function (): void {
    // The guard is code, not instruction. A model that invents "17 September 1948" against
    // passages that never mention it has produced the one error this product cannot ship.
    FakeAi::install('The merger happened on 17 September 1948 exactly.', corpus());

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->assertDontSee('17 September 1948');
});

it('warns about thin coverage before a generation is spent', function (): void {
    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('paper', 'Paper IV — Telangana Movement')
        ->set('topic', 'Mobilisation phase, 1971-1990')
        ->assertSee(__('We hold little indexed material on this topic, so the notes will be thin. It is on our list to fill.'));
});

it('stops warning once the corpus is deep enough', function (): void {
    foreach (range(1, 5) as $i) {
        AiChunk::create([
            'source_type' => 'material',
            'source_id' => $i,
            'source_locale' => 'te',
            'chunk_index' => 0,
            'content' => 'Mobilisation phase, 1971-1990 — detail '.$i,
            'checksum' => hash('sha256', (string) $i),
            // Tagged to the exam: coverage is measured per exam, because material indexed
            // for a different paper does not make this topic answerable.
            'metadata' => ['exam_id' => $this->exam->id],
        ]);
    }

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('paper', 'Paper IV — Telangana Movement')
        ->set('topic', 'Mobilisation phase, 1971-1990')
        ->assertDontSee(__('We hold little indexed material on this topic, so the notes will be thin. It is on our list to fill.'));
});

it('shows the monthly cap the student is working against', function (): void {
    GeneratedNote::factory()->count(3)->create(['user_id' => $this->user->id]);

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->assertSee('3')
        ->assertSee((string) config('ai.caps.free.notes_per_month'));
});

it('saves a snapshot of the body rather than a reference', function (): void {
    // A note the student saved must keep saying what it said, whatever happens to the
    // prompt version or the response cache afterwards.
    FakeAi::install("తెలంగాణ ఆలోచన దశ\n\nముఖ్య తేదీలు", corpus());

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->call('save')
        ->assertSee(__('Saved'));

    $note = GeneratedNote::where('user_id', $this->user->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toContain('ముఖ్య తేదీలు')
        ->and($note->title)->toBe('తెలంగాణ ఆలోచన దశ')
        ->and($note->sources)->toContain('Paper IV syllabus')
        ->and($note->exam_id)->toBe($this->exam->id);
});

it('will not save the same generation twice', function (): void {
    FakeAi::install('Notes on the topic.', corpus());

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->call('save')
        ->call('save');

    expect(GeneratedNote::where('user_id', $this->user->id)->count())->toBe(1);
});

it('refuses to save a refusal', function (): void {
    FakeAi::install('Notes', []);

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['examId' => $this->exam->id])
        ->set('topic', 'The idea of Telangana, 1948-1970')
        ->call('generate')
        ->call('save');

    expect(GeneratedNote::count())->toBe(0);
});

it('asks the model for different notes at each depth', function (): void {
    // Depth is not a length slider. Revision one-liners and detailed notes are different
    // instructions, not the same notes trimmed.
    $client = FakeAi::install('Notes', corpus());

    $generator = app(NotesGenerator::class);
    $generator->generate($this->user, 'A topic', 'revision');
    $generator->generate($this->user, 'A different topic', 'detailed');

    expect($client->questions[0])->toContain('one-liners')
        ->and($client->questions[1])->toContain('context')
        // The instruction the guard cannot fully check, repeated at every depth.
        ->and($client->questions[0])->toContain('Use only the supplied passages');
});

it('serves a repeated topic from cache instead of generating again', function (): void {
    // Notes are content-keyed, not user-keyed: a syllabus has a finite number of topics, so
    // one generation serves everyone who asks for it. This is what keeps the feature free.
    $client = FakeAi::install('Notes on the topic.', corpus());

    $generator = app(NotesGenerator::class);
    $generator->generate($this->user, 'The idea of Telangana, 1948-1970', 'exam_focused');

    $other = User::factory()->create();
    $second = $generator->generate($other, 'The idea of Telangana, 1948-1970', 'exam_focused');

    expect($client->questions)->toHaveCount(1)
        ->and($second->fromCache)->toBeTrue();
});

it('keeps one students notes out of anothers reach', function (): void {
    $note = GeneratedNote::factory()->create(['user_id' => User::factory()]);

    $this->actingAs($this->user)
        ->get(route('notes.show', ['locale' => 'te', 'note' => $note->id]))
        ->assertNotFound();
});

it('lets a student delete a note completely', function (): void {
    // Private content nobody else has seen: erasure means gone, not anonymised.
    $note = GeneratedNote::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->delete(route('notes.destroy', ['locale' => 'te', 'note' => $note->id]))
        ->assertRedirect();

    expect(GeneratedNote::find($note->id))->toBeNull();
});

it('will not let one student delete anothers note', function (): void {
    $note = GeneratedNote::factory()->create(['user_id' => User::factory()]);

    $this->actingAs($this->user)
        ->delete(route('notes.destroy', ['locale' => 'te', 'note' => $note->id]))
        ->assertNotFound();

    expect(GeneratedNote::find($note->id))->not->toBeNull();
});

it('keeps private pages out of the index', function (): void {
    $this->actingAs($this->user)
        ->get(route('notes.index', ['locale' => 'te']))
        ->assertOk()
        ->assertSee('noindex', false)
        // No hreflang cluster on a personal page: it would weaken the reciprocity of the
        // exam hubs that actually carry the ranking.
        ->assertDontSee('hreflang', false);
});
