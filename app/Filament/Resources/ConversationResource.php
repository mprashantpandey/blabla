<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Models\Conversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Conversations';
    protected static ?string $navigationGroup = 'Bookings';
    protected static ?int $navigationSort = 3;

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
                Tables\Columns\TextColumn::make('rider.name')
                    ->label('Rider')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.city.name')
                    ->label('City')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.status')
                    ->label('Booking Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'accepted' => 'info',
                        'requested' => 'warning',
                        'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Conversation Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'closed' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('messages_count')
                    ->label('Messages')
                    ->counts('messages')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('booking.status')
                    ->label('Booking Status')
                    ->options([
                        'requested' => 'Requested',
                        'accepted' => 'Accepted',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\Filter::make('city_id')
                    ->form([
                        Forms\Components\Select::make('city_id')
                            ->label('City')
                            ->relationship('booking.city', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['city_id'],
                            fn (Builder $query, $cityId): Builder => $query->whereHas('booking', function ($q) use ($cityId) {
                                $q->where('city_id', $cityId);
                            })
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for read-only
            ])
            ->defaultSort('last_message_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Conversation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Conversation ID'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'closed' => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('last_message_at')
                            ->label('Last Message At')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Booking Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking.id')
                            ->label('Booking ID'),
                        Infolists\Components\TextEntry::make('booking.status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'accepted' => 'info',
                                'requested' => 'warning',
                                'cancelled', 'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('booking.city.name')
                            ->label('City'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Participants')
                    ->schema([
                        Infolists\Components\TextEntry::make('rider.name')
                            ->label('Rider'),
                        Infolists\Components\TextEntry::make('rider.phone')
                            ->label('Rider Phone'),
                        Infolists\Components\TextEntry::make('driver.name')
                            ->label('Driver'),
                        Infolists\Components\TextEntry::make('driver.phone')
                            ->label('Driver Phone'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Messages')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('messages')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('sender.name')
                                    ->label('Sender')
                                    ->default('System'),
                                Infolists\Components\TextEntry::make('message_type')
                                    ->label('Type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'system' => 'gray',
                                        'text' => 'primary',
                                    }),
                                Infolists\Components\TextEntry::make('body')
                                    ->label('Message'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Sent At')
                                    ->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
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
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Apply city scoping for city admins
        if (auth()->user()->hasRole('city_admin') && !auth()->user()->hasRole('super_admin')) {
            $cityIds = auth()->user()->cities->pluck('id')->toArray();
            $query->whereHas('booking', function ($q) use ($cityIds) {
                $q->whereIn('city_id', $cityIds);
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
}
