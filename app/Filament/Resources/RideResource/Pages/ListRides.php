<?php

namespace App\Filament\Resources\RideResource\Pages;

use App\Filament\Resources\RideResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRides extends ListRecords
{
    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Rides are created via mobile app
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\RidesStatsWidget::class,
        ];
    }
}
