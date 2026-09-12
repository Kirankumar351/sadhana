<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamNotificationResource\Pages;

use App\Filament\Resources\ExamNotificationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateExamNotification extends CreateRecord
{
    protected static string $resource = ExamNotificationResource::class;

    /**
     * New records always start as a draft.
     *
     * There is no path in this panel that creates something already published. Publishing
     * is a separate, deliberate action with its own checklist.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['slug'] = $data['slug'] ?: Str::slug((string) ($data['title']['en'] ?? Str::random(8)));

        return $data;
    }
}
