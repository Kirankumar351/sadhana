<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\NotificationPreference;
use App\Services\Push\PushBudget;
use Livewire\Component;

/**
 * Account settings — Web Portal screen 20.
 *
 * Mostly notification preferences, because that is the setting people actually come here
 * to change. `PushBudget` already reads these rows; until this screen existed the
 * preferences were writable only by the system, which meant a user could not turn anything
 * off without uninstalling.
 *
 * THE PAGE SHOWS THE CAP RATHER THAN HIDING IT. Telling someone we will never send more
 * than five notifications a day is worth more than any wording about "relevant updates" —
 * it is a specific promise they can check us against, and it is the single most common
 * reason people mute an app like this.
 */
class Settings extends Component
{
    public string $name = '';

    public string $locale = 'te';

    /** @var array<string, array{push: bool, whatsapp: bool}> */
    public array $preferences = [];

    public bool $saved = false;

    /**
     * Ordered by what a user loses by turning it off, which is also the order the push
     * budget uses when the day is full.
     *
     * @return array<string, array{label: string, help: string, whatsapp: bool}>
     */
    public function getTypesProperty(): array
    {
        return [
            'deadline' => [
                'label' => __('Deadline reminders'),
                'help' => __('Three days and one day before a job you saved closes. We recommend keeping this on.'),
                'whatsapp' => true,
            ],
            'new_notification' => [
                'label' => __('New jobs you qualify for'),
                'help' => __('Only for exams you follow, and only when you actually meet the criteria.'),
                'whatsapp' => true,
            ],
            'daily_quiz' => [
                'label' => __('Daily quiz at 7 AM'),
                'help' => __('Ten questions every morning.'),
                'whatsapp' => true,
            ],
            'streak' => [
                'label' => __('Streak reminder at 9 PM'),
                'help' => __('Only if you have a streak going and have not done the quiz that day.'),
                'whatsapp' => false,
            ],
            'current_affairs' => [
                'label' => __('Current affairs digest'),
                'help' => __('Yesterday\'s news filtered to what these exams ask. Off by default.'),
                'whatsapp' => false,
            ],
            'community' => [
                'label' => __('Replies to your doubts'),
                'help' => __('Batched, never more than one a day. Off by default.'),
                'whatsapp' => false,
            ],
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->locale = $user->preferred_locale;

        $existing = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('type');

        foreach (array_keys($this->types) as $type) {
            $row = $existing->get($type);

            $this->preferences[$type] = [
                // Match PushBudget's defaults exactly. A settings screen that disagrees
                // with what the system actually does is worse than no settings screen.
                'push' => $row?->push ?? ! in_array($type, ['current_affairs', 'community'], true),
                'whatsapp' => $row?->whatsapp ?? false,
            ];
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'in:te,en'],
        ]);

        $user = auth()->user();

        $user->forceFill([
            'name' => $this->name,
            'preferred_locale' => $this->locale,
        ])->save();

        foreach ($this->preferences as $type => $channels) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['push' => (bool) $channels['push'], 'whatsapp' => (bool) $channels['whatsapp']],
            );
        }

        $this->saved = true;
    }

    /**
     * Turn everything off at once.
     *
     * Offered plainly rather than buried, because the alternative someone reaches for is
     * muting the app at the operating system level — and that also silences the deadline
     * reminder for a job they had already decided to apply for.
     */
    public function muteAll(): void
    {
        foreach (array_keys($this->preferences) as $type) {
            $this->preferences[$type] = ['push' => false, 'whatsapp' => false];
        }

        $this->save();
    }

    public function render()
    {
        return view('livewire.account.settings', [
            'dailyCap' => PushBudget::DAILY_CAP,
        ]);
    }
}
