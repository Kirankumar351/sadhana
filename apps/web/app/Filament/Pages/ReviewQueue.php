<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\ExamNotification;
use App\Services\Ingestion\NotificationPublisher;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The review queue — Admin Portal screen 03.
 *
 * One scraped notification at a time: extracted fields on the left, the source document on
 * the right, and nothing published until a person has said the two match.
 *
 * WHY THIS SCREEN IS SPLIT. The extractor never guesses a date — that is the single most
 * important rule in the ingestion pipeline — which means the fields it leaves blank are
 * exactly the ones a human has to read off the source. Putting the source anywhere other
 * than beside the form means the reviewer opens a PDF in another tab, loses their place,
 * and starts trusting the extraction instead of checking it.
 *
 * THE SLA IS TWO HOURS AND IT IS A BUSINESS NUMBER, NOT A COURTESY. Being first to publish
 * is most of why we rank for a new notification, and ranking is the entire acquisition
 * strategy. Every hour late costs position that is not recoverable later.
 *
 * THE CHECKLIST IS NOT DECORATION. Age reference date, fees, eligibility and the official
 * link are the four things that silently mis-serve thousands of people when wrong — a wrong
 * reference date does not look like an error, it just quietly tells the wrong people they
 * are ineligible. So publishing is blocked until each has been confirmed by hand.
 */
class ReviewQueue extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Review queue';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.review-queue';

    /** Vol 2: two hours from scrape to publish, or the position is gone. */
    public const SLA_HOURS = 2;

    public ?int $notificationId = null;

    /** @var array<string, bool> */
    public array $confirmed = [
        'dates' => false,
        'eligibility' => false,
        'fees' => false,
        'link' => false,
    ];

    /**
     * The queue depth in the sidebar, so nobody has to remember to look.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::queue()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::pastSla() > 0 ? 'danger' : 'warning';
    }

    public function mount(): void
    {
        $this->notificationId ??= static::queue()->value('id');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Review queue');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $waiting = static::queue()->count();

        if ($waiting === 0) {
            return __('Nothing waiting.');
        }

        $oldest = static::queue()->value('created_at');

        return trans_choice(
            '{1} :count waiting · oldest is :age · SLA is :sla hours|[2,*] :count waiting · oldest is :age · SLA is :sla hours',
            $waiting,
            [
                'count' => $waiting,
                'age' => $oldest ? Carbon::parse($oldest)->diffForHumans(syntax: true) : '—',
                'sla' => self::SLA_HOURS,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $current = $this->notificationId === null
            ? null
            : ExamNotification::query()->with(['exam', 'source'])->find($this->notificationId);

        $queue = static::queue()->get(['id', 'slug', 'title', 'created_at']);
        $position = $queue->search(fn ($row): bool => $row->id === $this->notificationId);

        return [
            'current' => $current,
            'queue' => $queue,
            'position' => $position === false ? null : $position + 1,
            'pastSla' => static::pastSla(),
            'missing' => $current === null ? [] : $this->missingCriticalFields($current),
            'previousId' => $position === false ? null : ($queue[$position - 1]->id ?? null),
            'nextId' => $position === false ? null : ($queue[$position + 1]->id ?? null),
        ];
    }

    /**
     * Fields that must be filled by a person before this can be published.
     *
     * The age reference date leads the list because it is the one that fails quietly: a
     * notification with no reference date makes every age check fall back to the apply
     * deadline, which is usually months out, and thousands of people are told they qualify
     * when they do not.
     *
     * @return list<string>
     */
    private function missingCriticalFields(ExamNotification $notification): array
    {
        $missing = [];

        if ($notification->age_reference_date === null && $notification->max_age !== null) {
            $missing[] = __('Age reference date — every age check depends on it');
        }

        if ($notification->apply_end_date === null) {
            $missing[] = __('Last date to apply');
        }

        if (blank($notification->official_pdf_url) && blank($notification->source_url)) {
            $missing[] = __('A link to the official document');
        }

        return $missing;
    }

    public function open(int $id): void
    {
        $this->notificationId = $id;
        $this->confirmed = array_fill_keys(array_keys($this->confirmed), false);
    }

    /**
     * Skip without judging.
     *
     * A reviewer who cannot verify something right now — the official site is down, the PDF
     * is a scan — must be able to move on without either publishing it or rejecting it.
     * Forcing a decision is how unverified notifications get published.
     */
    public function skip(): void
    {
        $queue = static::queue()->pluck('id');
        $position = $queue->search($this->notificationId);

        $this->open((int) ($queue[$position + 1] ?? $queue->first()));
    }

    public function publish(NotificationPublisher $publisher): void
    {
        $notification = ExamNotification::find($this->notificationId);

        if ($notification === null) {
            return;
        }

        if (in_array(false, $this->confirmed, true)) {
            Notification::make()
                ->title(__('Confirm each line first'))
                ->body(__('These four are the ones that mis-serve people silently when they are wrong.'))
                ->warning()
                ->send();

            return;
        }

        if ($this->missingCriticalFields($notification) !== []) {
            Notification::make()
                ->title(__('Fill the missing fields first'))
                ->body(__('The extractor left them blank because it will not guess. They have to come off the source.'))
                ->danger()
                ->send();

            return;
        }

        $publisher->publish($notification, auth()->user());

        Notification::make()
            ->title(__('Published'))
            ->body(__('Live in both languages, and queued for the feed.'))
            ->success()
            ->send();

        $this->confirmed = array_fill_keys(array_keys($this->confirmed), false);
        $this->notificationId = static::queue()->value('id');
    }

    public function reject(?string $reason = null): void
    {
        $notification = ExamNotification::find($this->notificationId);

        if ($notification === null) {
            return;
        }

        $notification->update(['status' => 'cancelled']);

        Notification::make()
            ->title(__('Rejected'))
            ->body($reason ?? __('It stays in the archive so the scraper is not re-tested against it.'))
            ->send();

        $this->notificationId = static::queue()->value('id');
    }

    /**
     * @return Builder<ExamNotification>
     */
    private static function queue()
    {
        return ExamNotification::query()
            ->where('status', 'pending_review')
            // Oldest first, always. A queue worked newest-first breaks the SLA on exactly
            // the items already closest to breaching it.
            ->orderBy('created_at');
    }

    private static function pastSla(): int
    {
        return static::queue()
            ->where('created_at', '<', now()->subHours(self::SLA_HOURS))
            ->count();
    }
}
