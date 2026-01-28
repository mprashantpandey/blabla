<?php

namespace App\Filament\Resources\DriverProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Services\NotificationService;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Key'),
                Tables\Columns\TextColumn::make('label')
                    ->label('Document'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Document Number'),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiry Date')
                    ->date()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
                Tables\Columns\ImageColumn::make('file')
                    ->label('Preview')
                    ->getStateUsing(fn ($record) => $record->getFirstMediaUrl('file'))
                    ->visible(fn ($record) => $record->hasMedia('file') && str_contains($record->getFirstMedia('file')->mime_type, 'image')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->getFirstMediaUrl('file'))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasMedia('file')),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->approve(auth()->id());
                        Notification::make()
                            ->title('Document approved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status !== 'approved'),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->reject($data['reason'], auth()->id());
                        Notification::make()
                            ->title('Document rejected')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status !== 'rejected'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}

