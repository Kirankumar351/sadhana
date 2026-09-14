<?php

declare(strict_types=1);

use App\Livewire\Community\AskDoubt;
use App\Livewire\Material\UploadNotes;
use App\Models\Exam;
use App\Models\Material;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * File uploads, end to end.
 *
 * THE BUG THESE GUARD AGAINST. Doubt attachments were written to the `public` disk while
 * the thread rendered them through `Storage::url()`, which resolves the DEFAULT disk — so
 * the file landed in storage/app/public and the page looked for it in storage/app/private.
 * Every photo and every voice note on every doubt was a broken image icon.
 *
 * It was invisible in two directions: the upload succeeded, the row saved, and the only
 * symptom was a picture that did not load. Nothing threw. And it broke the feature that
 * exists precisely for people who find typing Telugu on a phone keyboard painful.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->exam = Exam::factory()->create();
});

it('stores a doubt photo where the thread actually reads it', function (): void {
    Storage::fake(config('filesystems.default'));

    Livewire::actingAs($this->user)
        ->test(AskDoubt::class)
        ->set('title', 'How is age relaxation calculated for BC candidates?')
        ->set('body', 'I have read the notification twice and I still cannot work this out.')
        ->set('image', UploadedFile::fake()->image('textbook-page.jpg'))
        ->call('submit');

    $post = Post::where('user_id', $this->user->id)->firstOrFail();

    expect($post->image_path)->not->toBeNull()
        // Written and read through the same disk. This is the whole bug.
        ->and(Storage::disk(config('filesystems.default'))->exists($post->image_path))->toBeTrue();
});

it('stores a voice note the same way', function (): void {
    Storage::fake(config('filesystems.default'));

    Livewire::actingAs($this->user)
        ->test(AskDoubt::class)
        ->set('title', 'Doubt about the Gentlemen agreement of 1956')
        ->set('body', 'Recording this because typing it in Telugu takes me ten minutes.')
        ->set('audio', UploadedFile::fake()->create('doubt.mp3', 200, 'audio/mpeg'))
        ->call('submit');

    $post = Post::where('user_id', $this->user->id)->firstOrFail();

    expect($post->audio_path)->not->toBeNull()
        ->and(Storage::disk(config('filesystems.default'))->exists($post->audio_path))->toBeTrue();
});

it('serves the attachment through the app', function (): void {
    Storage::fake(config('filesystems.default'));
    Storage::disk(config('filesystems.default'))->put('doubts/images/x.jpg', 'binary');

    $post = Post::create([
        'user_id' => $this->user->id,
        'slug' => 'a-doubt-with-a-photo',
        'title' => 'A doubt with a photo attached to it',
        'body' => 'The photo is the question, really.',
        'source_locale' => 'te',
        'status' => 'published',
        'image_path' => 'doubts/images/x.jpg',
    ]);

    $this->get(route('community.attachment', ['locale' => 'te', 'slug' => $post->slug, 'type' => 'image']))
        ->assertOk();
});

it('stops serving an attachment the moment the post is unpublished', function (): void {
    // The reason attachments do not sit on a public prefix: a takedown has to actually
    // take the file down, not just hide the row that points at it.
    Storage::fake(config('filesystems.default'));
    Storage::disk(config('filesystems.default'))->put('doubts/images/y.jpg', 'binary');

    $post = Post::create([
        'user_id' => $this->user->id,
        'slug' => 'a-doubt-taken-down',
        'title' => 'A doubt that gets taken down for copyright',
        'body' => 'A photograph of a page from a published textbook.',
        'source_locale' => 'te',
        'status' => 'published',
        'image_path' => 'doubts/images/y.jpg',
    ]);

    $url = route('community.attachment', ['locale' => 'te', 'slug' => $post->slug, 'type' => 'image']);
    $this->get($url)->assertOk();

    $post->update(['status' => 'hidden']);

    $this->get($url)->assertNotFound();
});

it('refuses an attachment type it does not serve', function (): void {
    // A path segment the visitor controls must never choose which directory we read from.
    $post = Post::create([
        'user_id' => $this->user->id,
        'slug' => 'a-plain-doubt',
        'title' => 'A doubt with no attachment on it at all',
        'body' => 'Just text, nothing else attached here.',
        'source_locale' => 'te',
        'status' => 'published',
    ]);

    $this->get('/te/doubts/'.$post->slug.'/attachment/video')->assertNotFound();
    $this->get(route('community.attachment', ['locale' => 'te', 'slug' => $post->slug, 'type' => 'image']))
        ->assertNotFound();
});

it('404s when the row outlives the file', function (): void {
    Storage::fake(config('filesystems.default'));

    $post = Post::create([
        'user_id' => $this->user->id,
        'slug' => 'a-doubt-with-a-missing-file',
        'title' => 'A doubt whose file was purged from storage',
        'body' => 'The row still points at something that is gone.',
        'source_locale' => 'te',
        'status' => 'published',
        'image_path' => 'doubts/images/gone.jpg',
    ]);

    $this->get(route('community.attachment', ['locale' => 'te', 'slug' => $post->slug, 'type' => 'image']))
        ->assertNotFound();
});

it('puts an uploaded note in quarantine, not anywhere public', function (): void {
    // Nothing a user uploaded is reachable until a person has approved it. An unmoderated
    // public prefix is how a pirated textbook gets served from our own domain.
    Storage::fake(config('filesystems.default'));

    Livewire::actingAs($this->user)
        ->test(UploadNotes::class)
        ->set('title', 'My handwritten Polity notes')
        ->set('exam_id', $this->exam->id)
        ->set('locale', 'te')
        ->set('file', UploadedFile::fake()->create('notes.pdf', 500, 'application/pdf'))
        ->set('copyright_confirmed', true)
        ->call('submit');

    $material = Material::where('uploaded_by', $this->user->id)->firstOrFail();

    expect($material->file_path)->toStartWith('quarantine/')
        ->and($material->status)->toBe('pending_review')
        ->and(Storage::disk(config('filesystems.default'))->exists($material->file_path))->toBeTrue()
        // The warranty is the evidence, not the checkbox.
        ->and($material->copyright_confirmed)->toBeTrue()
        ->and($material->copyright_confirmed_at)->not->toBeNull();
});

it('will not accept a note without the copyright warranty', function (): void {
    Storage::fake(config('filesystems.default'));

    Livewire::actingAs($this->user)
        ->test(UploadNotes::class)
        ->set('title', 'Notes I found on Telegram')
        ->set('exam_id', $this->exam->id)
        ->set('file', UploadedFile::fake()->create('notes.pdf', 500, 'application/pdf'))
        ->set('copyright_confirmed', false)
        ->call('submit')
        ->assertHasErrors('copyright_confirmed');

    expect(Material::count())->toBe(0);
});

it('rejects a file type that is neither a PDF nor a photo', function (): void {
    Storage::fake(config('filesystems.default'));

    Livewire::actingAs($this->user)
        ->test(UploadNotes::class)
        ->set('title', 'An executable pretending to be notes')
        ->set('exam_id', $this->exam->id)
        ->set('file', UploadedFile::fake()->create('notes.exe', 100, 'application/octet-stream'))
        ->set('copyright_confirmed', true)
        ->call('submit')
        ->assertHasErrors('file');

    expect(Material::count())->toBe(0);
});

it('has an r2 disk configured, because egress is the whole reason', function (): void {
    /**
     * CLAUDE.md names Cloudflare R2 and says why: zero egress. This product serves 25 MB
     * PDFs to people on 3G, repeatedly, for free — on S3 the bandwidth bill scales with
     * exactly the behaviour the product is trying to encourage.
     *
     * The .env carried R2_* variables that no config ever read, so the disk did not exist
     * and any Storage::disk('r2') call would have thrown in production.
     */
    expect(config('filesystems.disks.r2'))->not->toBeNull()
        ->and(config('filesystems.disks.r2.driver'))->toBe('s3')
        // R2 has no regions and requires path-style addressing.
        ->and(config('filesystems.disks.r2.use_path_style_endpoint'))->toBeTrue();
});

it('has the admin panel writing to the same disk the site reads from', function (): void {
    /**
     * Filament defaults its upload disk to `public` while the application's default is
     * `local`. An admin attaching an image to a post wrote it to storage/app/public; the
     * thread rendered it from storage/app/private. Broken image, no error, nothing to
     * indicate which half was wrong.
     */
    expect(config('filament.default_filesystem_disk'))->toBe(config('filesystems.default'));
});
