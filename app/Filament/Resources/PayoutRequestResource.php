<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutRequestResource\Pages;
use App\Models\PayoutRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use App\Services\PayoutService;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter as BaseFilter;
use Illuminate\Database\Eloquent\Model;

class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payout Requests';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 2;

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
                Tables\Columns\TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PayoutRequest $record): string => 
                        $record->driverProfile->user->phone ?? $record->driverProfile->user->email ?? ''
                    ),
                Tables\Columns\TextColumn::make('driverProfile.city.name')
                    ->label('City')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('INR', divideBy: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank' => 'success',
                        'razorpay' => 'blue',
                        'stripe' => 'purple',
                        'cash' => 'gray',
                        'manual' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'requested' => 'warning',
                        'approved' => 'info',
                        'processing' => 'primary',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payout_reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->limit(20)
                    ->tooltip(fn (PayoutRequest $record) => $record->payout_reference),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'requested' => 'Requested',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('method')
                    ->label('Method')
                    ->multiple()
                    ->options([
                        'bank' => 'Bank Transfer',
                        'razorpay' => 'Razorpay',
                        'stripe' => 'Stripe',
                        'cash' => 'Cash',
                        'manual' => 'Manual Processing',
                    ]),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('driverProfile.city', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('requested_at')
                    ->form([
                        Forms\Components\DatePicker::make('requested_from')
                            ->label('Requested From'),
                        Forms\Components\DatePicker::make('requested_until')
                            ->label('Requested Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['requested_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '>=', $date),
                            )
                            ->when(
                                $data['requested_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '<=', $date),
                            );
                    }),
                Filter::make('needs_action')
                    ->label('Needs Action')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereIn('status', ['requested', 'approved', 'processing'])
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Payout Request')
                    ->modalDescription(fn (PayoutRequest $record): string => 
                        "Are you sure you want to approve this payout request of " . 
                        number_format($record->amount, 2) . "?"
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note (Optional)')
                            ->rows(3)
                            ->helperText('Add any notes about this approval'),
                    ])
                    ->action(function (PayoutRequest $record, array $data) {
                        $payoutService = app(PayoutService::class);
                        $payoutService->approvePayout($record, auth()->user());
                        
                        Notification::make()
                            ->title('Payout approved')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PayoutRequest $record): bool => $record->status === 'requested'),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Payout Request')
                    ->modalDescription(fn (PayoutRequest $record): string => 
                        "Are you sure you want to reject this payout request of " . 
                        number_format($record->amount, 2) . "? The amount will be credited back to the driver's wallet."
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->helperText('This reason will be shown to the driver and stored as admin note'),
                    ])
                    ->action(function (PayoutRequest $record, array $data) {
                        $payoutService = app(PayoutService::class);
                        $payoutService->rejectPayout($record, auth()->user(), $data['reason']);
                        
                        Notification::make()
                            ->title('Payout rejected')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PayoutRequest $record): bool => 
                        in_array($record->status, ['requested', 'approved']) && auth()->user()->can('payouts.reject')
                    ),
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Payout as Paid')
                    ->modalDescription(fn (PayoutRequest $record): string => 
                        "Mark this payout of " . number_format($record->amount, 2) . " as completed."
                    )
                    ->form([
                        Forms\Components\TextInput::make('payout_reference')
                            ->label('Payout Reference')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Transaction ID, reference number, or payment confirmation code'),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note (Optional)')
                            ->rows(2),
                    ])
                    ->action(function (PayoutRequest $record, array $data) {
                        $payoutService = app(PayoutService::class);
                        $payoutService->markPaid($record, $data['payout_reference']);
                        
                        Notification::make()
                            ->title('Payout marked as paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PayoutRequest $record): bool => 
                        in_array($record->status, ['approved', 'processing']) && auth()->user()->can('payouts.mark_paid')
                    ),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Payout Request')
                    ->modalDescription('Cancel this payout request. The amount will be credited back to the driver\'s wallet.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (PayoutRequest $record, array $data) {
                        // Credit wallet back
                        $walletService = app(\App\Services\WalletService::class);
                        $walletService->credit(
                            $record->driverProfile,
                            $record->amount,
                            'refund',
                            null,
                            "Payout cancellation refund",
                            ['payout_request_id' => $record->id, 'reason' => $data['reason']]
                        );
                        
                        $record->status = 'cancelled';
                        $record->admin_note = $data['reason'];
                        $record->save();
                        
                        Notification::make()
                            ->title('Payout cancelled')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (PayoutRequest $record): bool => $record->status === 'requested'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export CSV')
                        ->columns([
                            Tables\Columns\TextColumn::make('id'),
                            Tables\Columns\TextColumn::make('driverProfile.user.name')->label('Driver'),
                            Tables\Columns\TextColumn::make('driverProfile.city.name')->label('City'),
                            Tables\Columns\TextColumn::make('amount')->label('Amount'),
                            Tables\Columns\TextColumn::make('method')->label('Method'),
                            Tables\Columns\TextColumn::make('status')->label('Status'),
                            Tables\Columns\TextColumn::make('requested_at')->label('Requested At'),
                            Tables\Columns\TextColumn::make('processed_at')->label('Processed At'),
                            Tables\Columns\TextColumn::make('payout_reference')->label('Reference'),
                        ]),
                ]),
            ])
            ->defaultSort('requested_at', 'desc')
            ->emptyStateHeading('No payout requests')
            ->emptyStateDescription('Payout requests from drivers will appear here.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payout Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('id')
                                    ->label('Payout ID'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money(fn (PayoutRequest $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                                    ->weight('bold')
                                    ->size('lg'),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'requested' => 'warning',
                                        'approved' => 'info',
                                        'processing' => 'primary',
                                        'paid' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'gray',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('method')
                                    ->label('Method')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'bank' => 'success',
                                        'razorpay' => 'blue',
                                        'stripe' => 'purple',
                                        'cash' => 'gray',
                                        'manual' => 'warning',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('requested_at')
                                    ->label('Requested At')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('processed_at')
                                    ->label('Processed At')
                                    ->dateTime()
                                    ->placeholder('Not processed yet'),
                                Infolists\Components\TextEntry::make('payout_reference')
                                    ->label('Payout Reference')
                                    ->placeholder('Not provided yet'),
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
                Infolists\Components\Section::make('Driver Wallet Snapshot')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('wallet_balance')
                                    ->label('Current Balance')
                                    ->money(fn (PayoutRequest $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn (PayoutRequest $record): float => 
                                        (float) ($record->driverProfile->wallet->balance ?? 0)
                                    ),
                                Infolists\Components\TextEntry::make('lifetime_earned')
                                    ->label('Lifetime Earned')
                                    ->money(fn (PayoutRequest $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn (PayoutRequest $record): float => 
                                        (float) ($record->driverProfile->wallet->lifetime_earned ?? 0)
                                    ),
                                Infolists\Components\TextEntry::make('lifetime_withdrawn')
                                    ->label('Lifetime Withdrawn')
                                    ->money(fn (PayoutRequest $record) => $record->driverProfile->city->currency_code ?? 'INR', divideBy: false)
                                    ->state(fn (PayoutRequest $record): float => 
                                        (float) ($record->driverProfile->wallet->lifetime_withdrawn ?? 0)
                                    ),
                            ]),
                    ])
                    ->collapsible(),
                Infolists\Components\Section::make('Related Transactions')
                    ->schema([
                        Infolists\Components\TextEntry::make('payout_transaction_info')
                            ->label('Payout Transaction')
                            ->state(function (PayoutRequest $record) {
                                $wallet = $record->driverProfile->wallet;
                                if (!$wallet) {
                                    return 'No wallet found';
                                }
                                
                                $transaction = $wallet->transactions()
                                    ->where('type', 'payout')
                                    ->where('created_at', '>=', $record->requested_at)
                                    ->where('amount', $record->amount)
                                    ->first();
                                
                                if (!$transaction) {
                                    return 'No matching transaction found';
                                }
                                
                                return "{$transaction->direction} - " . number_format($transaction->amount, 2) . " - {$transaction->description}";
                            })
                            ->placeholder('No transaction found'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_at')
                            ->label('Requested')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Current Status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('processed_at')
                            ->label('Processed')
                            ->dateTime()
                            ->placeholder('Not processed yet')
                            ->icon('heroicon-o-check-circle')
                            ->visible(fn (PayoutRequest $record) => $record->processed_at !== null),
                    ])
                    ->collapsible(),
                Infolists\Components\Section::make('Admin Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_note')
                            ->label('')
                            ->placeholder('No admin notes'),
                    ])
                    ->visible(fn (PayoutRequest $record) => !empty($record->admin_note))
                    ->collapsible()
                    ->collapsed(),
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
            'index' => Pages\ListPayoutRequests::route('/'),
            'view' => Pages\ViewPayoutRequest::route('/{record}'),
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
}
