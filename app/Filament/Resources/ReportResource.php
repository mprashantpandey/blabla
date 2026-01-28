<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\Report;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Services\ReportService;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationLabel = 'Reports';
    protected static ?string $navigationGroup = 'Trust & Safety';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Update Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'reviewed' => 'Reviewed',
                                'action_taken' => 'Action Taken',
                                'dismissed' => 'Dismissed',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->rows(3)
                            ->required(fn ($get) => $get('status') === 'action_taken')
                            ->helperText('Required when status is "Action Taken"'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reportedUser.name')
                    ->label('Reported User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'danger',
                        'ride' => 'warning',
                        'message' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'reviewed' => 'warning',
                        'action_taken' => 'success',
                        'dismissed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.id')
                    ->label('Booking ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'reviewed' => 'Reviewed',
                        'action_taken' => 'Action Taken',
                        'dismissed' => 'Dismissed',
                    ]),
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'spam' => 'Spam',
                        'harassment' => 'Harassment',
                        'fraud' => 'Fraud',
                        'unsafe' => 'Unsafe',
                        'other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'user' => 'User',
                        'ride' => 'Ride',
                        'message' => 'Message',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                Infolists\Components\Section::make('Report Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Report ID'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'danger',
                                'reviewed' => 'warning',
                                'action_taken' => 'success',
                                'dismissed' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Reason')
                            ->badge(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Users')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter.name')
                            ->label('Reporter'),
                        Infolists\Components\TextEntry::make('reporter.email')
                            ->label('Reporter Email'),
                        Infolists\Components\TextEntry::make('reportedUser.name')
                            ->label('Reported User'),
                        Infolists\Components\TextEntry::make('reportedUser.email')
                            ->label('Reported User Email'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Booking')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking.id')
                            ->label('Booking ID')
                            ->placeholder('No booking associated'),
                    ])
                    ->visible(fn ($record) => $record->booking_id !== null),
                Infolists\Components\Section::make('Comment')
                    ->schema([
                        Infolists\Components\TextEntry::make('comment')
                            ->label('Reporter Comment')
                            ->placeholder('No comment provided'),
                    ])
                    ->visible(fn ($record) => !empty($record->comment)),
                Infolists\Components\Section::make('Admin Note')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_note')
                            ->label('')
                            ->placeholder('No admin note'),
                    ])
                    ->visible(fn ($record) => !empty($record->admin_note)),
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
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
        ];
    }
}
