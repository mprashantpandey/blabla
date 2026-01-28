<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RideResource\Pages;
use App\Models\Ride;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Rides';
    protected static ?string $navigationGroup = 'Rides';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ride Information')
                    ->schema([
                        Forms\Components\Select::make('driver_profile_id')
                            ->relationship('driverProfile', 'id', fn ($query) => $query->where('status', 'approved'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('city_id')
                            ->relationship('city', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('City')
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin_name')
                    ->label('Origin')
                    ->limit(30),
                Tables\Columns\TextColumn::make('destination_name')
                    ->label('Destination')
                    ->limit(30),
                Tables\Columns\TextColumn::make('departure_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('seats_total')
                    ->label('Total Seats'),
                Tables\Columns\TextColumn::make('seats_available')
                    ->label('Available')
                    ->badge()
                    ->color(fn ($record) => $record->seats_available > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('price_per_seat')
                    ->money('currency_code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\Filter::make('departure_at')
                    ->form([
                        Forms\Components\DatePicker::make('departure_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('departure_to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['departure_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('departure_at', '>=', $date),
                            )
                            ->when(
                                $data['departure_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('departure_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('price')
                    ->form([
                        Forms\Components\TextInput::make('min_price')
                            ->label('Min Price')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_price')
                            ->label('Max Price')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_price'],
                                fn (Builder $query, $price): Builder => $query->where('price_per_seat', '>=', $price),
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $price): Builder => $query->where('price_per_seat', '<=', $price),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Ride $record, array $data) {
                        $record->cancel($data['reason']);
                        Notification::make()
                            ->title('Ride cancelled')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Ride $record) => in_array($record->status, ['draft', 'published'])),
                Tables\Actions\Action::make('complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Ride $record) {
                        $record->markCompleted();
                        Notification::make()
                            ->title('Ride marked as completed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Ride $record) => $record->status === 'published'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->defaultSort('departure_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRides::route('/'),
            'view' => Pages\ViewRide::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // City Admin scope
        if (auth()->user()->hasRole('City Admin') && !auth()->user()->hasRole('Super Admin')) {
            $assignedCityIds = auth()->user()->assignedCities()->pluck('cities.id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        return $query;
    }
}
