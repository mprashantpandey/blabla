<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverWalletResource\Pages;
use App\Models\DriverWallet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

class DriverWalletResource extends Resource
{
    protected static ?string $model = DriverWallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationLabel = 'Driver Wallets';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only form
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DriverWallet $record): string => 
                        $record->driverProfile->user->phone ?? $record->driverProfile->user->email ?? ''
                    ),
                Tables\Columns\TextColumn::make('driverProfile.city.name')
                    ->label('City')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                    ->sortable()
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('lifetime_earned')
                    ->label('Lifetime Earned')
                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('lifetime_withdrawn')
                    ->label('Lifetime Withdrawn')
                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('driverProfile.city', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('has_balance')
                    ->label('Has Balance')
                    ->query(fn (Builder $query): Builder => $query->where('balance', '>', 0))
                    ->toggle(),
                Filter::make('has_pending_payouts')
                    ->label('Has Pending Payouts')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('driverProfile.payoutRequests', function ($q) {
                            $q->whereIn('status', ['requested', 'approved', 'processing']);
                        });
                    })
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for read-only
            ])
            ->defaultSort('last_updated_at', 'desc')
            ->emptyStateHeading('No wallets')
            ->emptyStateDescription('Driver wallets will appear here once drivers are approved.')
            ->emptyStateIcon('heroicon-o-wallet');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Wallet Summary')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('balance')
                                    ->label('Current Balance')
                                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('lifetime_earned')
                                    ->label('Lifetime Earned')
                                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false),
                                Infolists\Components\TextEntry::make('lifetime_withdrawn')
                                    ->label('Lifetime Withdrawn')
                                    ->money(fn (DriverWallet $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false),
                            ]),
                    ]),
                Infolists\Components\Section::make('Driver Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('driverProfile.user.name')
                                    ->label('Driver Name'),
                                Infolists\Components\TextEntry::make('driverProfile.user.email')
                                    ->label('Email'),
                                Infolists\Components\TextEntry::make('driverProfile.user.phone')
                                    ->label('Phone'),
                                Infolists\Components\TextEntry::make('driverProfile.city.name')
                                    ->label('City')
                                    ->badge(),
                            ]),
                    ]),
                Infolists\Components\Section::make('Recent Transactions')
                    ->schema([
                        Infolists\Components\TextEntry::make('transactions_count')
                            ->label('Total Transactions')
                            ->state(fn (DriverWallet $record) => $record->transactions()->count()),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DriverWalletResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverWallets::route('/'),
            'view' => Pages\ViewDriverWallet::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Apply city scoping for city admins
        $user = auth()->user();
        if ($user && $user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereHas('driverProfile', function ($q) use ($assignedCityIds) {
                $q->whereIn('city_id', $assignedCityIds);
            });
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('payouts.view') ?? false;
    }
}
