<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Answer;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Reputation, and the privileges it unlocks.
 *
 * Modelled on Stack Overflow because the mechanics are proven, but with a much gentler
 * tone. Our users are under exam stress and family pressure; a system that punishes a bad
 * question the way Stack Overflow does would empty this community in a month.
 *
 * TWO RULES THAT MATTER MORE THAN THE NUMBERS:
 *
 *   1. AI ANSWERS EARN NOTHING. The doubt solver writes into the same table as humans, and
 *      awarding it reputation would put a machine on the leaderboard above people who
 *      actually cleared the exam. It also cannot be marked best.
 *
 *   2. A VERIFIED SELECTED CANDIDATE OUTRANKS EVERYTHING. That badge is the single
 *      strongest trust signal in the product and is worth a manual verification process.
 */
final class ReputationService
{
    /** Deliberately asymmetric: gaining is easier than losing, except for real harm. */
    public const POINTS = [
        'answer_upvoted' => 10,
        'answer_best' => 25,
        'question_upvoted' => 5,
        'answer_downvoted' => -2,
        'post_removed' => -20,
        'material_approved' => 15,
    ];

    /** @var array<string, int> */
    public const PRIVILEGES = [
        'downvote' => 50,
        'edit_tags' => 200,
        'flag' => 300,
        'edit_posts' => 800,
        'moderate' => 1500,
    ];

    public function award(User $user, string $action): void
    {
        $points = self::POINTS[$action] ?? 0;

        if ($points === 0) {
            return;
        }

        // Floor at zero. A negative reputation number is a scarlet letter, and this
        // audience does not need another reason to stop participating.
        DB::transaction(function () use ($user, $points): void {
            $new = max(0, $user->reputation + $points);
            $user->forceFill(['reputation' => $new])->saveQuietly();
        });
    }

    public function can(User $user, string $privilege): bool
    {
        // Staff and verified selected candidates bypass the ladder. Someone who has
        // actually cleared the exam has earned more standing than any point total.
        if ($user->is_staff || $user->is_verified_selected) {
            return true;
        }

        return $user->reputation >= (self::PRIVILEGES[$privilege] ?? PHP_INT_MAX);
    }

    /**
     * Record a vote and move reputation.
     *
     * One vote per user per item, and changing your mind reverses the previous award
     * rather than stacking on top of it.
     */
    public function vote(User $voter, Model $votable, int $value): bool
    {
        if ($value === -1 && ! $this->can($voter, 'downvote')) {
            return false;
        }

        $author = $votable->user;

        // Voting on your own post is the oldest gaming vector there is.
        if ($author === null || $author->id === $voter->id) {
            return false;
        }

        // An AI answer can be voted on — that is useful signal about answer quality — but
        // it has no author to award, so nothing moves.
        $awardable = ! ($votable instanceof Answer && $votable->is_ai);

        return DB::transaction(function () use ($voter, $votable, $value, $author, $awardable): bool {
            $existing = Vote::query()
                ->where('user_id', $voter->id)
                ->where('votable_type', $votable->getMorphClass())
                ->where('votable_id', $votable->getKey())
                ->first();

            $action = $votable instanceof Post ? 'question_upvoted' : 'answer_upvoted';

            $previous = (int) ($existing->value ?? 0);

            if ($previous === $value) {
                return false;   // already voted this way
            }

            /**
             * One delta, applied once.
             *
             * Changing +1 to -1 must move the score by two, not by one. Doing it as a
             * decrement followed by an increment reads naturally and is subtly wrong,
             * because the two operations race on a stale in-memory attribute. Computing
             * the difference is both correct and obviously correct.
             */
            $votable->increment('upvotes', $value - $previous);

            if ($existing !== null) {
                $existing->update(['value' => $value]);

                // Undo the reputation the previous vote awarded.
                if ($awardable) {
                    $this->award($author, $previous > 0 ? 'answer_downvoted' : $action);
                }
            } else {
                Vote::create([
                    'user_id' => $voter->id,
                    'votable_type' => $votable->getMorphClass(),
                    'votable_id' => $votable->getKey(),
                    'value' => $value,
                ]);
            }

            if ($awardable) {
                $this->award($author, $value > 0 ? $action : 'answer_downvoted');
            }

            return true;
        });
    }

    /**
     * Mark an answer as the accepted one.
     *
     * Only the person who asked may do this. An AI answer can never be marked best — a
     * machine answer carrying the accepted tick would tell every future reader that it is
     * as trustworthy as a verified candidate's, which is exactly the line we do not blur.
     */
    public function markBest(Post $post, Answer $answer, User $actor): bool
    {
        if ($post->user_id !== $actor->id || $answer->post_id !== $post->id) {
            return false;
        }

        if ($answer->is_ai) {
            return false;
        }

        return DB::transaction(function () use ($post, $answer): bool {
            Answer::query()->where('post_id', $post->id)->update(['is_best' => false]);

            $answer->update(['is_best' => true]);
            $post->update(['best_answer_id' => $answer->id]);

            if ($answer->user !== null) {
                $this->award($answer->user, 'answer_best');
            }

            return true;
        });
    }
}
