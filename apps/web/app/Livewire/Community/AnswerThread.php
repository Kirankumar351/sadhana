<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use App\Models\Answer;
use App\Models\Post;
use App\Services\Community\ReputationService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Answering, voting and accepting.
 *
 * The ordering rule is the whole point of this component: AI answers sort below every
 * human answer regardless of votes, and a verified selected candidate is visually
 * distinguished from everyone else. That badge is the strongest trust signal the community
 * has, and burying it under a fluent machine answer would waste it.
 */
class AnswerThread extends Component
{
    #[Locked]
    public int $postId;

    public string $body = '';

    public function mount(Post $post): void
    {
        $this->postId = $post->id;
    }

    public function getPostProperty(): Post
    {
        return Post::query()
            ->with([
                'answers' => fn ($q) => $q->where('status', 'published')
                    ->with('user:id,name,reputation,is_verified_selected')
                    ->orderBy('is_ai')
                    ->orderByDesc('is_best')
                    ->orderByDesc('upvotes'),
            ])
            ->findOrFail($this->postId);
    }

    public function submit(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'min:20', 'max:8000'],
        ], [
            'body.min' => __('Please write a bit more — a one-line answer usually raises more questions.'),
        ]);

        $post = $this->post;

        Answer::create([
            'post_id' => $post->id,
            'user_id' => auth()->id(),
            'body' => $this->body,
            'source_locale' => app()->getLocale(),
        ]);

        $post->increment('answer_count');

        $this->reset('body');
    }

    public function vote(int $answerId, int $value, ReputationService $reputation): void
    {
        $answer = Answer::findOrFail($answerId);

        if (! $reputation->vote(auth()->user(), $answer, $value)) {
            // Silent no-op rather than an error. The common causes — voting on your own
            // answer, or not yet having downvote privileges — are not worth interrupting
            // someone for, and explaining them invites argument.
            return;
        }
    }

    /**
     * Accept an answer. Only the asker, and never an AI answer.
     */
    public function accept(int $answerId, ReputationService $reputation): void
    {
        $post = $this->post;
        $answer = Answer::findOrFail($answerId);

        $reputation->markBest($post, $answer, auth()->user());
    }

    public function render()
    {
        return view('livewire.community.answer-thread', ['post' => $this->post]);
    }
}
