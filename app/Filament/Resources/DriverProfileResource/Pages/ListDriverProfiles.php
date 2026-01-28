<?php

namespace App\Filament\Resources\DriverProfileResource\Pages;

use App\Filament\Resources\DriverProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDriverProfiles extends ListRecords
{
    protected static string $resource = DriverProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Drivers apply through mobile app, not admin panel
        ];
    }
}
