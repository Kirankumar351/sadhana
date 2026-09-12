<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamNotificationResource\Pages;

use App\Filament\Resources\ExamNotificationResource;
use App\Models\ExamNotification;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExamNotifications extends ListRecords
{
    protected static string $resource = ExamNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    /**
     * The review queue comes first, not the published list.
     *
     * Publish latency is a tracked metric with a 60-minute p90 target, and being first to
     * publish a breaking notification matters enormously for search ranking. Opening this
     * screen on "everything" would bury the only tab with a deadline attached to it.
     *
     * NOTE ON THE CLOSURE PARAMETER NAME. Filament resolves these closures by parameter
     * NAME, so it must be `$query`. Calling it `$q` compiles and looks fine, but Filament
     * cannot match it, passes null, and the table then fails to resolve its own model —
     * with an error that points at Filament internals rather than at this file.
     */
    public function getTabs(): array
    {
        return [
            'review' => Tab::make('Awaiting review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending_review'))
                ->badge(fn (): ?int => ExamNotification::query()->where('status', 'pending_review')->count() ?: null)
                ->badgeColor('warning'),

            'drafts' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),

            'published' => Tab::make('Published')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')),

            /**
             * Published but never verified. This is the state that puts a wrong date in
             * front of a student, so it gets its own tab and a red badge rather than
             * living behind a filter someone has to remember to apply.
             */
            'unverified' => Tab::make('Unverified')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')->whereNull('verified_at'))
                ->badge(fn (): ?int => ExamNotification::query()
                    ->where('status', 'published')
                    ->whereNull('verified_at')
                    ->count() ?: null)
                ->badgeColor('danger'),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'review';
    }
}
