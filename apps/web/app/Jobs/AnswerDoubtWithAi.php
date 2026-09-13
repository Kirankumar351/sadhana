<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Answer;
use App\Models\Post;
use App\Services\AI\AiGateway;
use App\Services\AI\AiResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The AI doubt solver — AI Layer feature 02.
 *
 * Answers a posted doubt within seconds so nobody waits three hours for something the
 * material already covers. The value is speed on the easy half; the community remains the
 * answer for everything else.
 *
 * THIS IS THE ONLY AI FEATURE THAT WRITES INTO A USER-FACING TABLE, which is why the
 * constraints are unusually tight:
 *
 *   - it writes `is_ai = 1`, and the thread sorts AI below every human answer regardless
 *     of votes, so a verified selected candidate is never buried under a machine
 *   - it earns no reputation and can never be marked as the accepted answer
 *   - below the confidence floor it writes NOTHING. A hedging machine answer sitting at the
 *     top of an empty thread actively discourages the human who would have answered
 *     properly — it looks answered, so nobody opens it.
 *
 * Queued rather than inline: a student posting a doubt should not wait on a model call
 * before their own question appears.
 */
class AnswerDoubtWithAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $postId)
    {
        // Below push and high. An instant answer is valuable; it is never more urgent
        // than a notification alert going out.
        $this->onQueue('default');
    }

    public function handle(AiGateway $gateway): void
    {
        if (! config('ai.features.doubt_solver.enabled')) {
            return;
        }

        $post = Post::query()->with('exam')->find($this->postId);

        if ($post === null || $post->status !== 'published') {
            return;
        }

        // Never answer twice, and never answer something a person has already handled.
        if ($post->answers()->exists()) {
            return;
        }

        $result = $gateway->ask(
            question: $post->title."\n\n".$post->body,
            user: null,
            feature: 'doubt_solver',
            context: array_filter(['exam_id' => $post->exam_id]),
            locale: $post->source_locale,
        );

        if (! $this->shouldPost($result)) {
            Log::info('ai.doubt_solver.declined', [
                'post' => $post->slug,
                'status' => $result->status,
            ]);

            return;
        }

        Answer::create([
            'post_id' => $post->id,
            // No author. An AI answer has no user to credit or to award.
            'user_id' => null,
            'body' => $this->withSources($result),
            'source_locale' => $post->source_locale,
            'is_ai' => true,
        ]);

        $post->increment('answer_count');
    }

    /**
     * Silence is a valid and often correct outcome.
     *
     * A low-confidence machine answer at the top of a thread is worse than an empty
     * thread: it looks answered, so the person who actually knows scrolls past.
     */
    private function shouldPost(AiResult $result): bool
    {
        return $result->isAnswer()
            && $result->sources !== []
            && $result->confidence >= (float) config('ai.guardrails.confidence_floor');
    }

    /**
     * Append the sources the answer was built from.
     *
     * A citation a reader can check is the difference between grounding and decoration,
     * and it is also how a community member spots that the machine quoted the wrong
     * notification.
     */
    private function withSources(AiResult $result): string
    {
        $body = $result->text;

        $titles = array_values(array_filter(array_map(
            static fn ($passage) => $passage->title,
            $result->sources,
        )));

        if ($titles === []) {
            return $body;
        }

        return $body."\n\n"
            .__('Based on:')."\n"
            .implode("\n", array_map(static fn (string $t): string => '• '.$t, array_unique($titles)));
    }
}
