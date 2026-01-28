<?php

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Services\ReportService;

class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate admin note requirement
        if ($data['status'] === 'action_taken' && empty($data['admin_note'])) {
            Notification::make()
                ->title('Admin note required')
                ->body('Admin note is required when status is "Action Taken"')
                ->danger()
                ->send();
            
            $this->halt();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $reportService = app(ReportService::class);
        $reportService->updateStatus(
            $this->record,
            $this->record->status,
            $this->record->admin_note
        );
    }
}
