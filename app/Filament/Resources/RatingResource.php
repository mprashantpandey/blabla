<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RatingResource\Pages;
use App\Models\Rating;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Services\RatingService;

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Ratings';
    protected static ?string $navigationGroup = 'Trust & Safety';
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
                Tables\Columns\TextColumn::make('booking.id')
                    ->label('Booking ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rater.name')
                    ->label('Rater')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ratee.name')
                    ->label('Rated User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'rider_to_driver' => 'Rider → Driver',
                        'driver_to_rider' => 'Driver → Rider',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'rider_to_driver' => 'info',
                        'driver_to_rider' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . ' ' . $state)
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->comment),
                Tables\Columns\IconColumn::make('is_hidden')
                    ->label('Hidden')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'rider_to_driver' => 'Rider → Driver',
                        'driver_to_rider' => 'Driver → Rider',
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        1 => '1 star',
                        2 => '2 stars',
                        3 => '3 stars',
                        4 => '4 stars',
                        5 => '5 stars',
                    ]),
                Tables\Filters\TernaryFilter::make('is_hidden')
                    ->label('Hidden')
                    ->placeholder('All')
                    ->trueLabel('Hidden only')
                    ->falseLabel('Visible only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Rating $record) => !$record->is_hidden)
                    ->action(function (Rating $record) {
                        $ratingService = app(RatingService::class);
                        $ratingService->hideRating($record, true);
                        Notification::make()
                            ->title('Rating hidden')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('unhide')
                    ->label('Unhide')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Rating $record) => $record->is_hidden)
                    ->action(function (Rating $record) {
                        $ratingService = app(RatingService::class);
                        $ratingService->hideRating($record, false);
                        Notification::make()
                            ->title('Rating unhidden')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Rating Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Rating ID'),
                        Infolists\Components\TextEntry::make('rating')
                            ->label('Rating')
                            ->badge()
                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state) . ' ' . $state)
                            ->color(fn (int $state): string => match (true) {
                                $state >= 4 => 'success',
                                $state >= 3 => 'warning',
                                default => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('role')
                            ->label('Role')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'rider_to_driver' => 'Rider → Driver',
                                'driver_to_rider' => 'Driver → Rider',
                                default => $state,
                            }),
                        Infolists\Components\IconEntry::make('is_hidden')
                            ->label('Hidden')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Users')
                    ->schema([
                        Infolists\Components\TextEntry::make('rater.name')
                            ->label('Rater'),
                        Infolists\Components\TextEntry::make('rater.email')
                            ->label('Rater Email'),
                        Infolists\Components\TextEntry::make('ratee.name')
                            ->label('Rated User'),
                        Infolists\Components\TextEntry::make('ratee.email')
                            ->label('Rated User Email'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Booking')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking.id')
                            ->label('Booking ID'),
                        Infolists\Components\TextEntry::make('booking.status')
                            ->label('Status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('ride.origin_name')
                            ->label('Origin'),
                        Infolists\Components\TextEntry::make('ride.destination_name')
                            ->label('Destination'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Comment')
                    ->schema([
                        Infolists\Components\TextEntry::make('comment')
                            ->label('')
                            ->placeholder('No comment provided'),
                    ])
                    ->visible(fn ($record) => !empty($record->comment)),
            ]);
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
            'index' => Pages\ListRatings::route('/'),
            'view' => Pages\ViewRating::route('/{record}'),
        ];
    }
}
