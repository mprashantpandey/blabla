<?php

namespace App\Filament\Resources\DriverWalletResource\Pages;

use App\Filament\Resources\DriverWalletResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverWallet extends ViewRecord
{
    protected static string $resource = DriverWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No actions for read-only
        ];
    }
}
