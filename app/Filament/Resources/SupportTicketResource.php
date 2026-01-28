<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Services\SupportService;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?string $navigationLabel = 'Support Tickets';
    protected static ?string $navigationGroup = 'Support';
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
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(50)
                    ->weight('bold')
                    ->wrap()
                    ->tooltip(fn (SupportTicket $record) => $record->subject),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SupportTicket $record): string => 
                        $record->user->email ?? $record->user->phone ?? ''
                    ),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('City')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_id')
                    ->label('Booking')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '—')
                    ->url(fn (SupportTicket $record) => $record->booking_id 
                        ? route('filament.admin.resources.bookings.view', $record->booking_id)
                        : null
                    )
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->multiple()
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ]),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('has_booking')
                    ->label('Has Booking')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('booking_id'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Reply to Support Ticket')
                    ->modalDescription(fn (SupportTicket $record): string => 
                        "Reply to ticket: {$record->subject}"
                    )
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(5)
                            ->maxLength(5000)
                            ->helperText('Your reply will be sent to the user'),
                    ])
                    ->action(function (SupportTicket $record, array $data) {
                        $supportService = app(SupportService::class);
                        $user = auth()->user();
                        $isAdmin = $user->hasAnyRole(['super_admin', 'city_admin', 'support_staff']);
                        $supportService->addReply($record, $user, $data['message'], $isAdmin);
                        
                        Notification::make()
                            ->title('Reply sent')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user()->can('support.reply')),
                Tables\Actions\Action::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ])
                            ->required()
                            ->default(fn (SupportTicket $record) => $record->status),
                    ])
                    ->action(function (SupportTicket $record, array $data) {
                        $supportService = app(SupportService::class);
                        $supportService->updateStatus($record, $data['status']);
                        
                        Notification::make()
                            ->title('Status updated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => auth()->user()->can('support.change_status')),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No support tickets')
            ->emptyStateDescription('Support tickets from users will appear here.')
            ->emptyStateIcon('heroicon-o-lifebuoy');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ticket Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('id')
                                    ->label('Ticket ID'),
                                Infolists\Components\TextEntry::make('subject')
                                    ->label('Subject')
                                    ->weight('bold')
                                    ->size('lg'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'open' => 'warning',
                                        'in_progress' => 'info',
                                        'resolved' => 'success',
                                        'closed' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'open' => 'Open',
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        'closed' => 'Closed',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('priority')
                                    ->label('Priority')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'low' => 'gray',
                                        'medium' => 'warning',
                                        'high' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),
                Infolists\Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Name'),
                                Infolists\Components\TextEntry::make('user.email')
                                    ->label('Email'),
                                Infolists\Components\TextEntry::make('user.phone')
                                    ->label('Phone')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('city.name')
                                    ->label('City')
                                    ->badge()
                                    ->placeholder('—'),
                            ]),
                    ]),
                Infolists\Components\Section::make('Booking Reference')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking_id')
                            ->label('Booking ID')
                            ->formatStateUsing(fn ($state) => $state ? "#{$state}" : 'No booking reference')
                            ->url(fn (SupportTicket $record) => $record->booking_id 
                                ? route('filament.admin.resources.bookings.view', $record->booking_id)
                                : null
                            )
                            ->openUrlInNewTab(),
                    ])
                    ->visible(fn (SupportTicket $record) => $record->booking_id !== null)
                    ->collapsible()
                    ->collapsed(),
                Infolists\Components\Section::make('Message Timeline')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('messages')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('sender_type')
                                    ->label('From')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'user' => 'info',
                                        'admin' => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                Infolists\Components\TextEntry::make('sender.name')
                                    ->label('Sender')
                                    ->placeholder('System'),
                                Infolists\Components\TextEntry::make('message')
                                    ->label('Message')
                                    ->columnSpanFull()
                                    ->formatStateUsing(fn ($state) => nl2br(e($state))),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Date')
                                    ->dateTime(),
                            ])
                            ->columns(3),
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
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
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
            $query->whereIn('city_id', $assignedCityIds);
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
        return auth()->user()?->can('support.view') ?? false;
    }
}
