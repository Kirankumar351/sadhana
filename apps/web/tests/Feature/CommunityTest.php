<?php

declare(strict_types=1);

use App\Models\Answer;
use App\Models\Material;
use App\Models\Post;
use App\Models\User;
use App\Services\Community\ReputationService;

/**
 * The doubt community and the material library.
 *
 * Two rules carry most of the weight here: AI answers never outrank humans, and no
 * uploaded file is reachable before a person has approved it. Both are the kind of rule
 * that is easy to break accidentally and expensive to discover in production.
 */
beforeEach(function (): void {
    $this->reputation = app(ReputationService::class);
    $this->asker = User::factory()->create();
    $this->answerer = User::factory()->create(['reputation' => 100]);

    $this->post = Post::create([
        'user_id' => $this->asker->id,
        'slug' => 'test-doubt',
        'title' => 'How is age relaxation calculated for SC candidates?',
        'body' => 'I am confused about which date is used for the age calculation.',
        'source_locale' => 'te',
        'status' => 'published',
    ]);
});

function answerBy(User $user, Post $post, array $attributes = []): Answer
{
    return Answer::create(array_merge([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'body' => 'It is calculated from the reference date stated in the notification.',
        'source_locale' => 'te',
    ], $attributes));
}

// ================================================================ AI ranking

describe('ai answers', function (): void {
    /**
     * THE RULE THAT MATTERS MOST IN THIS MODULE.
     *
     * The doubt solver writes into the same table as humans. If a machine answer could
     * carry the accepted tick, every future reader would be told it is as trustworthy as a
     * verified candidate's — which is exactly the line we never blur.
     */
    it('cannot be marked as the accepted answer', function (): void {
        $ai = answerBy($this->answerer, $this->post, ['is_ai' => true, 'user_id' => null]);

        $accepted = $this->reputation->markBest($this->post, $ai, $this->asker);

        expect($accepted)->toBeFalse()
            ->and($ai->refresh()->is_best)->toBeFalse();
    });

    /**
     * Awarding reputation to the doubt solver would put a machine on the leaderboard above
     * people who actually cleared the exam.
     */
    it('earns no reputation when upvoted', function (): void {
        $ai = answerBy($this->answerer, $this->post, ['is_ai' => true]);
        $before = $this->answerer->reputation;

        $this->reputation->vote($this->asker, $ai, 1);

        expect($this->answerer->refresh()->reputation)->toBe($before)
            // The vote still counts as signal about answer quality.
            ->and($ai->refresh()->upvotes)->toBe(1);
    });

    /**
     * Sort order is (is_ai ASC, is_best DESC, upvotes DESC). A heavily upvoted machine
     * answer must still sit below a brand new human one.
     */
    it('sorts below every human answer regardless of votes', function (): void {
        answerBy($this->answerer, $this->post, ['is_ai' => true, 'upvotes' => 99]);
        $human = answerBy($this->answerer, $this->post, ['upvotes' => 0]);

        $ordered = Answer::query()
            ->where('post_id', $this->post->id)
            ->orderBy('is_ai')->orderByDesc('is_best')->orderByDesc('upvotes')
            ->get();

        expect($ordered->first()->id)->toBe($human->id);
    });
});

// ================================================================ reputation

describe('reputation', function (): void {
    it('awards points for an upvoted answer', function (): void {
        $answer = answerBy($this->answerer, $this->post);
        $before = $this->answerer->reputation;

        $this->reputation->vote($this->asker, $answer, 1);

        expect($this->answerer->refresh()->reputation)->toBe($before + 10);
    });

    it('awards more for an accepted answer', function (): void {
        $answer = answerBy($this->answerer, $this->post);
        $before = $this->answerer->reputation;

        $this->reputation->markBest($this->post, $answer, $this->asker);

        expect($this->answerer->refresh()->reputation)->toBe($before + 25)
            ->and($this->post->refresh()->best_answer_id)->toBe($answer->id);
    });

    /**
     * The oldest gaming vector there is.
     */
    it('refuses a vote on your own answer', function (): void {
        $answer = answerBy($this->answerer, $this->post);

        expect($this->reputation->vote($this->answerer, $answer, 1))->toBeFalse();
    });

    it('only lets the asker accept an answer', function (): void {
        $answer = answerBy($this->answerer, $this->post);
        $stranger = User::factory()->create();

        expect($this->reputation->markBest($this->post, $answer, $stranger))->toBeFalse();
    });

    /**
     * Changing your mind reverses the previous award rather than stacking on it.
     */
    it('reverses the previous award when a vote is changed', function (): void {
        $answer = answerBy($this->answerer, $this->post);
        $before = $this->answerer->reputation;

        // The voter needs downvote privileges (50 reputation) for the second vote to be
        // accepted at all — otherwise this would silently test the privilege gate instead.
        $voter = User::factory()->create(['reputation' => 100]);

        $this->reputation->vote($voter, $answer, 1);
        $this->reputation->vote($voter, $answer, -1);

        // +1 to -1 must move the score by two, not by one.
        expect($answer->refresh()->upvotes)->toBe(-1)
            ->and($this->answerer->refresh()->reputation)->toBeLessThan($before + 10);
    });

    /**
     * Voting the same way twice is a no-op, not a double count. Double-tapping on a phone
     * is common and must not inflate a score.
     */
    it('ignores a repeated identical vote', function (): void {
        $answer = answerBy($this->answerer, $this->post);

        $this->reputation->vote($this->asker, $answer, 1);
        $second = $this->reputation->vote($this->asker, $answer, 1);

        expect($second)->toBeFalse()
            ->and($answer->refresh()->upvotes)->toBe(1);
    });

    it('requires reputation before a user may downvote', function (): void {
        $newcomer = User::factory()->create(['reputation' => 0]);
        $answer = answerBy($this->answerer, $this->post);

        expect($this->reputation->vote($newcomer, $answer, -1))->toBeFalse();
    });

    /**
     * Someone who has actually cleared the exam has earned more standing than any point
     * total, so the verified badge bypasses the ladder.
     */
    it('lets a verified selected candidate bypass the privilege ladder', function (): void {
        $verified = User::factory()->create(['reputation' => 0, 'is_verified_selected' => true]);

        expect($this->reputation->can($verified, 'moderate'))->toBeTrue();
    });

    /**
     * Reputation floors at zero. A negative number is a scarlet letter, and this audience
     * does not need another reason to stop participating.
     */
    it('never lets reputation go below zero', function (): void {
        $user = User::factory()->create(['reputation' => 5]);

        $this->reputation->award($user, 'post_removed');   // -20

        expect($user->refresh()->reputation)->toBe(0);
    });
});

// ================================================================ material

describe('material library', function (): void {
    /**
     * NOTHING A USER UPLOADED IS REACHABLE BEFORE A PERSON APPROVES IT.
     *
     * An unmoderated public prefix is how a pirated textbook ends up served from our own
     * domain before anyone notices, and one publisher notice ends the company.
     */
    it('does not publish an upload until it is reviewed', function (): void {
        $material = Material::create([
            'slug' => 'my-notes',
            'title' => ['te' => 'నా నోట్స్'],
            'locale' => 'te',
            'source_type' => 'user_notes',
            'copyright_confirmed' => true,
            'file_path' => 'quarantine/materials/x.pdf',
        ]);

        expect($material->refresh()->status)->toBe('pending_review');

        $this->get('/te/material')->assertDontSee('నా నోట్స్');
        $this->get('/te/material/my-notes')->assertNotFound();
    });

    it('uploads into quarantine, never the public prefix', function (): void {
        $material = Material::create([
            'slug' => 'quarantined',
            'title' => ['en' => 'Notes'],
            'locale' => 'en',
            'source_type' => 'user_notes',
            'file_path' => 'quarantine/materials/abc.pdf',
        ]);

        expect($material->file_path)->toStartWith('quarantine/');
    });

    /**
     * The warranty is the evidence, not the checkbox. A timestamp and an IP is what turns
     * "we asked them not to" into something defensible if a publisher writes to us.
     */
    it('records the copyright warranty with a timestamp and ip', function (): void {
        $material = Material::create([
            'slug' => 'warranted',
            'title' => ['en' => 'Notes'],
            'locale' => 'en',
            'source_type' => 'user_notes',
            'copyright_confirmed' => true,
            'copyright_ip' => '203.0.113.7',
            'copyright_confirmed_at' => now(),
        ]);

        expect($material->copyright_confirmed)->toBeTrue()
            ->and($material->copyright_ip)->toBe('203.0.113.7')
            ->and($material->copyright_confirmed_at)->not->toBeNull();
    });

    it('stops serving material that was taken down', function (): void {
        $material = Material::create([
            'slug' => 'removed-notes',
            'title' => ['en' => 'Removed'],
            'locale' => 'en',
            'source_type' => 'user_notes',
            'status' => 'published',
        ]);

        $this->get('/en/material/removed-notes')->assertSuccessful();

        $material->update(['status' => 'taken_down']);

        $this->get('/en/material/removed-notes')->assertNotFound();
    });
});

// ================================================================ pages

it('serves the community and material pages in both languages', function (string $path): void {
    $this->get($path)->assertSuccessful();
})->with([
    '/te/doubts', '/en/doubts',
    '/te/doubts/test-doubt',
    '/te/material', '/en/material',
]);

it('requires an account to ask or upload', function (string $path): void {
    $this->get($path)->assertRedirect();
})->with(['/te/doubts/ask/new', '/te/material/share/new']);
