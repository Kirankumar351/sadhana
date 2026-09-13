<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ScrapeSource;
use App\Services\Ingestion\ScrapeResult;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A scrape source has failed three runs in a row.
 *
 * Written to be actionable at 6 AM by someone who is not fully awake: what broke, how long
 * it has been broken, and the one thing that matters — publish it by hand in the meantime.
 * Never let a broken scraper delay a notification.
 */
class ScraperBroken extends Notification
{
    public function __construct(
        private readonly ScrapeSource $source,
        private readonly ScrapeResult $result,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lastSuccess = $this->source->last_success_at?->diffForHumans() ?? 'never';

        return (new MailMessage)
            ->error()
            ->subject("Scraper broken: {$this->source->name}")
            ->line("{$this->source->name} has failed {$this->source->consecutive_failures} runs in a row.")
            ->line("Last successful run: {$lastSuccess}")
            ->line('Error: '.$this->result->summary())
            ->line('Government sites change layout without warning. Check whether the page moved, the markup changed, or we are being rate-limited.')
            ->line('IN THE MEANTIME: publish anything urgent by hand through the admin panel. Never let a broken scraper delay a notification — being first is what wins the search ranking.')
            ->action('Open the review queue', url('/admin/exam-notifications'));
    }
}
