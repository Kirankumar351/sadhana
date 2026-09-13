<?php

declare(strict_types=1);

namespace App\Livewire\Quiz;

use App\Models\QuizAttempt;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Share a quiz result to WhatsApp status.
 *
 * Vol 2 ch.8.3 calls this "the cheapest acquisition channel in the product", and it is
 * worth taking seriously for a specific reason: a WhatsApp status seen by a hundred
 * classmates costs us nothing and carries the one proof that matters — a real person, in
 * this district, doing this every morning.
 *
 * THE COPY IS WHAT MAKES OR BREAKS IT. A share that reads as an advertisement gets deleted;
 * one that reads as something a person would actually type gets posted. So the text leads
 * with the score and the streak, mentions the product once, and never says anything like
 * "join me on Sadhana".
 *
 * Deliberately never auto-shares. A product that posts to someone's status on their behalf
 * is a product they uninstall.
 */
class ShareResult extends Component
{
    #[Locked]
    public int $attemptId;

    public bool $open = false;

    public function mount(QuizAttempt $attempt): void
    {
        $this->attemptId = $attempt->id;
    }

    public function getAttemptProperty(): QuizAttempt
    {
        return QuizAttempt::findOrFail($this->attemptId);
    }

    /**
     * The share text.
     *
     * Written in the user's own language, because a Telugu speaker posting an English
     * status about a Telugu app is not what actually happens — and the whole value of the
     * share is that it looks like the person, not like us.
     */
    public function getShareTextProperty(): string
    {
        $attempt = $this->attempt;
        $streak = auth()->user()?->streak?->current_streak ?? 0;
        $locale = app()->getLocale();

        $score = $attempt->correctCount().'/'.(int) $attempt->total_marks;

        if ($locale === 'te') {
            $lines = ["ఈరోజు రోజు ప్రశ్నలో {$score} 🎯"];

            if ($streak > 1) {
                $lines[] = "{$streak} రోజుల స్ట్రీక్ 🔥";
            }

            $lines[] = 'రోజూ ఉదయం 10 ప్రశ్నలు, తెలుగులో — sadhana.study';

            return implode("\n", $lines);
        }

        $lines = ["Scored {$score} on today's quiz 🎯"];

        if ($streak > 1) {
            $lines[] = "{$streak}-day streak 🔥";
        }

        $lines[] = 'Ten questions every morning, in Telugu — sadhana.study';

        return implode("\n", $lines);
    }

    /**
     * WhatsApp deep link.
     *
     * `wa.me` opens the app on a phone and WhatsApp Web on a desktop, which covers both
     * without a device check. It opens a share sheet — it never sends anything on its own.
     */
    public function getWhatsAppUrlProperty(): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->shareText);
    }

    public function render()
    {
        return view('livewire.quiz.share-result');
    }
}
