<?php

namespace App\Filament\Resources\RideResource\Pages;

use App\Filament\Resources\RideResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewRide extends ViewRecord
{
    protected static string $resource = RideResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ride Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('driverProfile.user.name')
                            ->label('Driver'),
                        Infolists\Components\TextEntry::make('city.name')
                            ->label('City'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'published' => 'success',
                                'draft' => 'gray',
                                'cancelled' => 'danger',
                                'completed' => 'info',
                            }),
                        Infolists\Components\TextEntry::make('origin_name')
                            ->label('Origin'),
                        Infolists\Components\TextEntry::make('destination_name')
                            ->label('Destination'),
                        Infolists\Components\TextEntry::make('departure_at')
                            ->label('Departure')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('arrival_estimated_at')
                            ->label('Estimated Arrival')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('price_per_seat')
                            ->label('Price per Seat')
                            ->money('currency_code'),
                        Infolists\Components\TextEntry::make('seats_total')
                            ->label('Total Seats'),
                        Infolists\Components\TextEntry::make('seats_available')
                            ->label('Available Seats')
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->visible(fn ($record) => $record->notes),
                        Infolists\Components\TextEntry::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->visible(fn ($record) => $record->cancellation_reason),
                    ])->columns(2),
            ]);
    }
}

