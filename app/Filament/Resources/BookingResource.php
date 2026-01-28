<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Services\BookingService;
use App\Services\PaymentService;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Bookings';
    protected static ?string $navigationGroup = 'Bookings';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking Information')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'requested' => 'Requested',
                                'accepted' => 'Accepted',
                                'rejected' => 'Rejected',
                                'payment_pending' => 'Payment Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                                'expired' => 'Expired',
                                'refunded' => 'Refunded',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rider.name')
                    ->label('Rider')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ride.origin_name')
                    ->label('Origin')
                    ->limit(30),
                Tables\Columns\TextColumn::make('ride.destination_name')
                    ->label('Destination')
                    ->limit(30),
                Tables\Columns\TextColumn::make('seats_requested')
                    ->label('Seats'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('currency_code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'gray',
                        'razorpay' => 'blue',
                        'stripe' => 'purple',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'requested' => 'warning',
                        'payment_pending' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'danger',
                        'completed' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'accepted' => 'Accepted',
                        'payment_pending' => 'Payment Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'razorpay' => 'Razorpay',
                        'stripe' => 'Stripe',
                    ]),
                Tables\Filters\Filter::make('pending_approval')
                    ->label('Pending Approval')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'requested')),
                Tables\Filters\Filter::make('payment_pending')
                    ->label('Payment Pending')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'payment_pending')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('force_cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $bookingService = app(BookingService::class);
                        $bookingService->cancelBooking($record, auth()->user(), $data['reason']);
                        Notification::make()
                            ->title('Booking cancelled')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Booking $record) => !in_array($record->status, ['cancelled', 'completed', 'expired'])),
                Tables\Actions\Action::make('force_refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Refund Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $paymentService = app(PaymentService::class);
                        $refunded = $paymentService->processRefund($record, $data['reason']);
                        if ($refunded) {
                            $record->status = 'refunded';
                            $record->refunded_at = now();
                            $record->save();
                            Notification::make()
                                ->title('Refund processed')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Refund failed')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Booking $record) => $record->payment_status === 'paid' && $record->payment_method !== 'cash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // City Admin scope
        $user = auth()->user();
        if ($user && $user->hasRole('city_admin') && !$user->hasRole('super_admin')) {
            $assignedCityIds = \App\Models\CityAdminAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('city_id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        return $query;
    }
}
