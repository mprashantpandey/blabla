<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverProfileResource\Pages;
use App\Filament\Resources\DriverProfileResource\RelationManagers;
use App\Models\DriverProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Services\NotificationService;

class DriverProfileResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Driver Profiles';
    protected static ?string $navigationGroup = 'Drivers';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Driver Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('city_id')
                            ->relationship('city', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('status')
                            ->options([
                                'not_applied' => 'Not Applied',
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'suspended' => 'Suspended',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('applied_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('missing_docs_count')
                    ->label('Missing Docs')
                    ->counts('documents', function ($query) {
                        $requiredDocs = \App\Models\SystemSetting::get('driver.required_documents', '[]');
                        $requiredDocs = is_string($requiredDocs) ? json_decode($requiredDocs, true) : $requiredDocs;
                        $requiredKeys = collect($requiredDocs)->where('required', true)->pluck('key');
                        return $query->whereNotIn('key', $requiredKeys);
                    })
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'not_applied' => 'Not Applied',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\Filter::make('needs_review')
                    ->label('Needs Review')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->where('status', 'pending')
                          ->orWhereHas('documents', function ($docQ) {
                              $docQ->where('status', 'pending');
                          });
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note (Optional)'),
                    ])
                    ->action(function (DriverProfile $record, array $data) {
                        $record->admin_note = $data['admin_note'] ?? null;
                        $record->updateStatus('approved', null, auth()->id());

                        $notificationService = app(NotificationService::class);
                        $notificationService->sendToUser(
                            $record->user,
                            'Driver Application Approved',
                            'Congratulations! Your driver application has been approved.',
                            ['type' => 'driver_approved'],
                            true
                        );

                        Notification::make()
                            ->title('Driver approved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DriverProfile $record) => $record->status !== 'approved'),
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
                    ->action(function (DriverProfile $record, array $data) {
                        $record->updateStatus('rejected', $data['reason'], auth()->id());

                        $notificationService = app(NotificationService::class);
                        $notificationService->sendToUser(
                            $record->user,
                            'Driver Application Rejected',
                            'Your application was rejected. Reason: ' . $data['reason'],
                            ['type' => 'driver_rejected', 'reason' => $data['reason']],
                            true
                        );

                        Notification::make()
                            ->title('Driver rejected')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DriverProfile $record) => in_array($record->status, ['pending', 'approved'])),
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (DriverProfile $record, array $data) {
                        $record->updateStatus('suspended', $data['reason'], auth()->id());

                        $notificationService = app(NotificationService::class);
                        $notificationService->sendToUser(
                            $record->user,
                            'Driver Account Suspended',
                            'Your driver account has been suspended. Reason: ' . $data['reason'],
                            ['type' => 'driver_suspended', 'reason' => $data['reason']],
                            true
                        );

                        Notification::make()
                            ->title('Driver suspended')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DriverProfile $record) => $record->status === 'approved'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('applied_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\VehiclesRelationManager::class,
            RelationManagers\VerificationEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverProfiles::route('/'),
            'view' => Pages\ViewDriverProfile::route('/{record}'),
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
