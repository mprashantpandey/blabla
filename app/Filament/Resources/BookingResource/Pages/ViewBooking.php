<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('rider.name')
                            ->label('Rider'),
                        Infolists\Components\TextEntry::make('driverProfile.user.name')
                            ->label('Driver'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'requested' => 'warning',
                                'payment_pending' => 'warning',
                                'cancelled' => 'danger',
                                'expired' => 'danger',
                                'completed' => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('seats_requested')
                            ->label('Seats Requested'),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money('currency_code'),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method'),
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge(),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Ride Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('ride.origin_name')
                            ->label('Origin'),
                        Infolists\Components\TextEntry::make('ride.destination_name')
                            ->label('Destination'),
                        Infolists\Components\TextEntry::make('ride.departure_at')
                            ->label('Departure')
                            ->dateTime(),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('events')
                            ->schema([
                                Infolists\Components\TextEntry::make('event')
                                    ->label('Event'),
                                Infolists\Components\TextEntry::make('performed_by')
                                    ->label('Performed By'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Time')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}

