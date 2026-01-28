<?php

namespace App\Filament\Resources\DriverProfileResource\Pages;

use App\Filament\Resources\DriverProfileResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDriverProfile extends ViewRecord
{
    protected static string $resource = DriverProfileResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Driver Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('user.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('city.name')
                            ->label('City'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('dob')
                            ->label('Date of Birth')
                            ->date(),
                        Infolists\Components\TextEntry::make('address')
                            ->label('Address'),
                        Infolists\Components\ImageEntry::make('selfie')
                            ->label('Selfie')
                            ->getStateUsing(fn ($record) => $record->getFirstMediaUrl('selfie'))
                            ->visible(fn ($record) => $record->hasSelfie()),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Admin Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_note')
                            ->label('Admin Note')
                            ->visible(fn ($record) => $record->admin_note),
                        Infolists\Components\TextEntry::make('rejected_reason')
                            ->label('Rejection Reason')
                            ->visible(fn ($record) => $record->rejected_reason),
                    ]),
                Infolists\Components\Section::make('Wallet Summary')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('wallet.balance')
                                    ->label('Current Balance')
                                    ->money(fn ($record) => $record->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn ($record) => $record->wallet?->balance ?? 0)
                                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('wallet.lifetime_earned')
                                    ->label('Lifetime Earned')
                                    ->money(fn ($record) => $record->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn ($record) => $record->wallet?->lifetime_earned ?? 0),
                                Infolists\Components\TextEntry::make('wallet.lifetime_withdrawn')
                                    ->label('Lifetime Withdrawn')
                                    ->money(fn ($record) => $record->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn ($record) => $record->wallet?->lifetime_withdrawn ?? 0),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->wallet !== null)
                    ->collapsible(),
            ]);
    }
}

