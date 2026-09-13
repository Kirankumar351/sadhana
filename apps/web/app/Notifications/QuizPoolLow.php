<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The question pool cannot fill tomorrow's quiz.
 *
 * This is the alert that protects the retention engine. The daily quiz is what brings
 * people back at 7 AM, and a morning with no quiz breaks the habit for everyone at once —
 * which is far more damaging than it sounds, because a broken streak rarely restarts.
 */
class QuizPoolLow extends Notification
{
    public function __construct(
        private readonly int $available,
        private readonly int $needed,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('No quiz tomorrow: only '.$this->available.' questions available')
            ->line("The daily quiz needs {$this->needed} servable questions and the pool has {$this->available}.")
            ->line('Servable means: answer key approved by a person, not disputed, and present in Telugu. A question missing its Telugu translation does not count, and that is usually what has happened.')
            ->line('The quiz has NOT been published. Fix the pool and re-run: php artisan quiz:publish')
            ->action('Open the question bank', url('/admin/questions'));
    }
}
