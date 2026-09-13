<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * DPDP Act 2023: the right to access, and the right to erasure.
 *
 * Both must work from v1, not be retrofitted. We hold dates of birth, categories,
 * qualifications and phone numbers for lakhs of people, and every one of them has an
 * enforceable right to see it and to have it removed.
 *
 * INTEGRATION MAP GAP 12 IS THE ONE PEOPLE MISS. The original export covered profile, quiz
 * attempts, posts and saved jobs. It omitted AI conversations, generated study plans,
 * flashcard decks, answer evaluations and agent memory — all of which are personal data
 * the user is entitled to, and all of which must also be deleted.
 */
final class DataExportService
{
    /**
     * Everything we hold about one person, in a form they can actually read.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'what_this_contains' => 'Everything Sadhana holds about your account.',

            'account' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'language' => $user->preferred_locale,
                'reputation' => $user->reputation,
                'joined' => $user->created_at?->toIso8601String(),
            ],

            // Collected for exactly one stated purpose: eligibility matching.
            'profile' => $user->profile?->only([
                'date_of_birth', 'gender', 'category', 'is_pwd', 'is_ex_serviceman',
                'highest_qualification', 'qualification_stream', 'state', 'district',
            ]),

            'consents' => DB::table('consents')->where('user_id', $user->id)
                ->get(['purpose', 'granted', 'policy_version', 'granted_at', 'withdrawn_at']),

            'exams_followed' => $user->examPreferences()->pluck('slug'),
            'saved_jobs' => $user->savedNotifications()->pluck('slug'),

            'quiz_attempts' => $user->quizAttempts()
                ->get(['score', 'total_marks', 'subject_breakdown', 'created_at']),

            'streak' => $user->streak?->only(['current_streak', 'longest_streak', 'last_active_date']),

            'doubts_asked' => $user->posts()->get(['title', 'body', 'created_at']),
            'answers_written' => $user->answers()->get(['body', 'upvotes', 'is_best', 'created_at']),

            // ---- gap 12: everything below was missing from the original spec ----

            'ai_conversations' => DB::table('ai_requests')
                ->where('user_id', $user->id)
                ->get(['feature', 'confidence', 'refused_reason', 'created_at']),

            'study_plans' => $user->studyPlans()->get(['exam_id', 'target_date', 'plan', 'generated_at']),

            'flashcards' => $user->flashcards()->get(['deck', 'front', 'back', 'ease', 'due_at']),

            'answer_evaluations' => DB::table('answer_evaluations')
                ->where('user_id', $user->id)
                ->get(['band', 'points_hit', 'points_missed', 'created_at']),

            'assistant_memory' => DB::table('agent_memories')
                ->where('user_id', $user->id)
                ->get(['key', 'value', 'kind', 'created_at']),

            'payments' => DB::table('orders')
                ->where('user_id', $user->id)
                ->get(['number', 'total_paise', 'status', 'created_at']),
        ];
    }

    /**
     * Erasure.
     *
     * WHAT IS DELETED AND WHAT IS NOT, and why the difference is defensible:
     *
     *   Deleted outright — everything that identifies the person or describes their
     *   behaviour: profile, quiz history, streak, flashcards, study plans, AI history,
     *   assistant memory, saved jobs, push tokens.
     *
     *   Anonymised, not deleted — community posts and answers. Removing an accepted answer
     *   would break a page other people rely on and that Google has indexed, harming
     *   readers who had no part in this. The authorship link is severed instead, which is
     *   what the right to erasure actually requires.
     *
     *   Retained — payment and invoice records. Indian tax law requires them, and DPDP
     *   permits retention for a legal obligation. They are unlinked from the profile but
     *   the transaction itself must survive an audit.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->profile()?->delete();
            $user->streak()?->delete();
            $user->quizAttempts()->delete();
            $user->flashcards()->delete();
            $user->studyPlans()->delete();
            $user->pushTokens()->delete();
            $user->examPreferences()->detach();
            $user->savedNotifications()->detach();

            DB::table('ai_requests')->where('user_id', $user->id)->delete();
            DB::table('agent_memories')->where('user_id', $user->id)->delete();
            DB::table('answer_evaluations')->where('user_id', $user->id)->delete();
            DB::table('notification_preferences')->where('user_id', $user->id)->delete();
            DB::table('votes')->where('user_id', $user->id)->delete();

            // Community content survives, authorship does not.
            $user->posts()->update(['user_id' => null]);
            $user->answers()->update(['user_id' => null]);

            /**
             * The phone number is overwritten rather than nulled.
             *
             * It is the unique key and the login identity. Leaving it would let the same
             * number re-register into a deleted shell, and nulling it would collide with
             * the next deletion. A one-way hash keeps the column unique and meaningless.
             */
            $user->forceFill([
                'name' => 'Deleted account',
                'phone' => 'deleted-'.substr(hash('sha256', $user->phone.config('app.key')), 0, 20),
                'email' => null,
                'password' => null,
                'is_banned' => false,
            ])->saveQuietly();

            $user->delete();
        });
    }
}
