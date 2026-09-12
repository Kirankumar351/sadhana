<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamNotificationResource\Pages;

use App\Filament\Resources\ExamNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExamNotification extends EditRecord
{
    protected static string $resource = ExamNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')
                ->label('View on site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => route('notifications.show', [
                    'locale' => config('locales.default'),
                    'slug' => $this->record->slug,
                ]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->status === 'published'),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Editing a published notification clears its verification.
     *
     * Someone signed off on the values that were there before; those values have now
     * changed, so the sign-off no longer applies to what is live. Forcing re-verification
     * is the difference between an audit trail and a decoration.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status === 'published' && $this->record->isDirty()) {
            $data['verified_at'] = null;
            $data['verified_by'] = null;
        }

        return $data;
    }
}
