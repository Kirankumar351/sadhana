<?php

declare(strict_types=1);

use App\Models\Answer;
use App\Models\Flashcard;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Services\Privacy\DataExportService;
use Illuminate\Support\Facades\DB;

/**
 * DPDP Act 2023 — access and erasure.
 *
 * Both must genuinely work, not exist as a sentence in a policy page. We hold dates of
 * birth, categories, qualifications and phone numbers for lakhs of people, and each of
 * them has an enforceable right to see it and to have it removed.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['name' => 'Ravi K']);

    Profile::create([
        'user_id' => $this->user->id,
        'date_of_birth' => '1999-06-15',
        'category' => 'sc',
        'highest_qualification' => 'degree',
        'state' => 'TS',
        'district' => 'Karimnagar',
    ]);

    $this->post = Post::create([
        'user_id' => $this->user->id,
        'slug' => 'my-doubt',
        'title' => 'A doubt other people rely on',
        'body' => 'The body of the question.',
        'source_locale' => 'te',
        'status' => 'published',
    ]);

    Flashcard::create([
        'user_id' => $this->user->id,
        'deck' => 'Polity',
        'front' => ['te' => 'ప్రశ్న'],
        'back' => ['te' => 'జవాబు'],
        'locale' => 'te',
        'source_type' => 'manual',
    ]);

    DB::table('ai_requests')->insert([
        'user_id' => $this->user->id,
        'feature' => 'ask',
        'model' => 'test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->service = app(DataExportService::class);
});

// ================================================================ access

it('exports the profile fields we actually collect', function (): void {
    $export = $this->service->export($this->user);

    expect($export['profile']['date_of_birth'])->not->toBeNull()
        ->and($export['profile']['category'])->toBe('sc')
        ->and($export['account']['phone'])->toBe($this->user->phone);
});

/**
 * INTEGRATION MAP GAP 12.
 *
 * The original export covered profile, quiz attempts, posts and saved jobs — and omitted
 * AI conversations, study plans, flashcards, answer evaluations and assistant memory. All
 * of it is personal data the user is entitled to, and omitting it is the difference
 * between complying and appearing to comply.
 */
it('includes the AI data the original spec omitted', function (string $key): void {
    expect($this->service->export($this->user))->toHaveKey($key);
})->with(['ai_conversations', 'study_plans', 'flashcards', 'answer_evaluations', 'assistant_memory']);

it('lets a signed-in user download their data', function (): void {
    $this->actingAs($this->user)
        ->get('/te/privacy/export')
        ->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename="sadhana-my-data.json"');
});

it('records that an export was made', function (): void {
    $this->actingAs($this->user)->get('/te/privacy/export');

    expect(DB::table('data_requests')->where('user_id', $this->user->id)->where('type', 'export')->exists())
        ->toBeTrue();
});

// ================================================================ erasure

it('deletes the profile and personal history', function (): void {
    $id = $this->user->id;

    $this->service->delete($this->user);

    expect(DB::table('profiles')->where('user_id', $id)->exists())->toBeFalse()
        ->and(DB::table('flashcards')->where('user_id', $id)->exists())->toBeFalse()
        ->and(DB::table('ai_requests')->where('user_id', $id)->exists())->toBeFalse();
});

/**
 * Community content survives; authorship does not.
 *
 * Deleting an accepted answer would break a page other people rely on and that Google has
 * indexed, harming readers who had no part in the request. Severing the authorship link is
 * what the right to erasure actually requires.
 */
it('anonymises posts rather than deleting them', function (): void {
    $this->service->delete($this->user);

    $post = Post::find($this->post->id);

    expect($post)->not->toBeNull()
        ->and($post->user_id)->toBeNull()
        ->and($post->title)->toBe('A doubt other people rely on');
});

/**
 * The phone number is the unique key and the login identity. Leaving it would let the same
 * number re-register into a deleted shell; nulling it would collide with the next
 * deletion. A one-way hash keeps the column unique and meaningless.
 */
it('makes the phone number unrecoverable without breaking uniqueness', function (): void {
    $original = $this->user->phone;

    $this->service->delete($this->user);

    $row = DB::table('users')->where('id', $this->user->id)->first();

    expect($row->phone)->not->toBe($original)
        ->and($row->phone)->toStartWith('deleted-')
        ->and($row->name)->toBe('Deleted account')
        ->and($row->deleted_at)->not->toBeNull();
});

it('allows a second account to delete without a key collision', function (): void {
    $other = User::factory()->create();

    $this->service->delete($this->user);
    $this->service->delete($other);

    expect(DB::table('users')->whereNotNull('deleted_at')->count())->toBe(2);
});

// ================================================================ the screen

it('requires typing the phone number to delete', function (): void {
    $this->actingAs($this->user)
        ->delete('/te/privacy', ['confirm_phone' => '0000000000'])
        ->assertSessionHasErrors('confirm_phone');

    expect(User::find($this->user->id))->not->toBeNull();
});

it('deletes when the phone number matches', function (): void {
    $this->actingAs($this->user)
        ->delete('/te/privacy', ['confirm_phone' => $this->user->phone])
        ->assertRedirect();

    expect(User::find($this->user->id))->toBeNull();
});

it('keeps the privacy page behind auth', function (): void {
    $this->get('/te/privacy')->assertRedirect();
    $this->actingAs($this->user)->get('/te/privacy')->assertSuccessful();
});

/**
 * Data minimisation is a legal requirement, not a preference. The export is the easiest
 * place to notice if someone ever adds a field that should not exist.
 */
it('holds no aadhaar, caste certificate or full address', function (): void {
    $export = $this->service->export($this->user);
    $serialised = json_encode($export);

    expect(strtolower($serialised))
        ->not->toContain('aadhaar')
        ->not->toContain('caste_certificate')
        ->not->toContain('address_line');
});
