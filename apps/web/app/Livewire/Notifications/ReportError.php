<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\NotificationErrorReport;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One-tap error reporting on a notification.
 *
 * Vol 1 ch.11.2 is blunt about why this exists: being right every time is not achievable,
 * and a visible correction path is worth more to credibility than pretending otherwise.
 * A student who spots a wrong last date and has no way to tell us learns that we do not
 * care — and tells other people so.
 *
 * Routed to a 2-hour SLA queue. The field they select is the useful part: "the last date
 * is wrong" is immediately actionable, where "something is wrong" needs a reviewer to read
 * the whole PDF again.
 *
 * OPEN TO GUESTS. The person most likely to catch a wrong date is someone who just read
 * the official PDF, and they will not create an account to tell us.
 */
class ReportError extends Component
{
    #[Locked]
    public int $notificationId;

    public bool $open = false;

    public bool $sent = false;

    public string $field = '';

    public string $note = '';

    public function mount(int $notificationId): void
    {
        $this->notificationId = $notificationId;
    }

    public function submit(): void
    {
        $this->validate([
            'field' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'field.required' => __('Please choose what is wrong.'),
        ]);

        NotificationErrorReport::create([
            'notification_id' => $this->notificationId,
            'user_id' => auth()->id(),
            'field' => $this->field,
            'note' => $this->note ?: null,
            'status' => 'open',
        ]);

        $this->sent = true;
        $this->reset(['field', 'note']);
    }

    /**
     * The fields worth distinguishing.
     *
     * Ordered by how much damage being wrong does. A wrong last date costs someone the
     * application entirely; a wrong vacancy count costs them nothing but our credibility.
     *
     * @return array<string, string>
     */
    public function getFieldsProperty(): array
    {
        return [
            'apply_end_date' => __('The last date is wrong'),
            'eligibility' => __('The age limit or qualification is wrong'),
            'official_pdf_url' => __('The official link is broken or wrong'),
            'total_vacancies' => __('The number of posts is wrong'),
            'expired' => __('This notification has been cancelled or withdrawn'),
            'other' => __('Something else'),
        ];
    }

    public function render()
    {
        return view('livewire.notifications.report-error');
    }
}
