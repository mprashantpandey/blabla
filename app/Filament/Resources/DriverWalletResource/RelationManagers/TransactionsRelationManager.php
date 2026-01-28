<?php

namespace App\Filament\Resources\DriverWalletResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'earning' => 'success',
                        'commission' => 'warning',
                        'refund' => 'info',
                        'adjustment' => 'gray',
                        'payout' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->driverWallet->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),
                Tables\Columns\TextColumn::make('booking_id')
                    ->label('Booking ID')
                    ->placeholder('—')
                    ->url(fn ($record) => $record->booking_id 
                        ? route('filament.admin.resources.bookings.view', $record->booking_id)
                        : null
                    )
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'earning' => 'Earning',
                        'commission' => 'Commission',
                        'refund' => 'Refund',
                        'adjustment' => 'Adjustment',
                        'payout' => 'Payout',
                    ]),
                SelectFilter::make('direction')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No transactions')
            ->emptyStateDescription('Wallet transactions will appear here.')
            ->emptyStateIcon('heroicon-o-arrow-path');
    }
}

