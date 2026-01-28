<?php

namespace App\Filament\Resources\ServiceAreaResource\Pages;

use App\Filament\Resources\ServiceAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceArea extends EditRecord
{
    protected static string $resource = ServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
