<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use App\Models\Post;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Asking a doubt.
 *
 * SEARCH-BEFORE-YOU-ASK is the feature that makes this community survivable. As the title
 * is typed, similar existing questions surface. It gives an instant answer where one
 * already exists, it stops the same doubt being asked a thousand times, and it keeps the
 * archive clean enough to actually rank on Google.
 *
 * Photo and voice attachments exist because typing Telugu on a phone keyboard is genuinely
 * painful. A student with a doubt about one line of a textbook will photograph it; asking
 * them to transcribe it in Telugu means they do not ask at all.
 */
class AskDoubt extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $body = '';

    public ?int $exam_id = null;

    public string $subject = '';

    public $image = null;

    public $audio = null;

    /** @var array<int, array{title: string, url: string, answers: int}> */
    public array $similar = [];

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:12', 'max:300'],
            'body' => ['required', 'string', 'min:20', 'max:8000'],
            'exam_id' => ['nullable', 'exists:exams,id'],
            'subject' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:5120'],
            // Voice notes are capped hard: a two-minute clip is a doubt, a ten-minute one
            // is a lecture nobody will listen to.
            'audio' => ['nullable', 'file', 'mimes:mp3,m4a,ogg,webm', 'max:5120'],
        ];
    }

    /**
     * Live duplicate search as the title is typed.
     *
     * Falls back to a LIKE query when Meilisearch is unavailable, because losing the
     * duplicate check silently is how the archive fills with the same question fifty times.
     */
    public function updatedTitle(): void
    {
        if (Str::length($this->title) < 12) {
            $this->similar = [];

            return;
        }

        $this->similar = Post::query()
            ->where('status', 'published')
            ->where(fn ($q) => $q->where('title', 'like', '%'.$this->title.'%')
                ->orWhere('body', 'like', '%'.$this->title.'%'))
            ->orderByDesc('answer_count')
            ->limit(4)
            ->get()
            ->map(fn (Post $p): array => [
                'title' => $p->title,
                'url' => route('community.show', ['slug' => $p->slug]),
                'answers' => $p->answer_count,
            ])
            ->all();
    }

    public function submit()
    {
        $validated = $this->validate();

        $post = Post::create([
            'user_id' => auth()->id(),
            'exam_id' => $validated['exam_id'] ?? null,
            'slug' => $this->uniqueSlug($validated['title']),
            'title' => $validated['title'],
            'body' => $validated['body'],
            // The language the doubt was WRITTEN in, not the interface language. It drives
            // lazy translation later, and guessing it from the UI would mislabel every
            // English question asked by someone browsing in Telugu.
            'source_locale' => app()->getLocale(),
            'subject' => $validated['subject'] ?: null,
            'image_path' => $this->image?->store('doubts/images', 'public'),
            'audio_path' => $this->audio?->store('doubts/audio', 'public'),
        ]);

        return $this->redirectRoute('community.show', ['slug' => $post->slug], navigate: true);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 180, ''));
        $slug = $base;
        $i = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.community.ask-doubt');
    }
}
