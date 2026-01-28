<?php

namespace App\Filament\Resources\PayoutRequestResource\Pages;

use App\Filament\Resources\PayoutRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Services\PayoutService;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewPayoutRequest extends ViewRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Payout Request')
                ->modalDescription(fn (): string => 
                    "Are you sure you want to approve this payout request of " . 
                    number_format($this->record->amount, 2) . "?"
                )
                ->form([
                    Forms\Components\Textarea::make('admin_note')
                        ->label('Admin Note (Optional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $payoutService = app(PayoutService::class);
                    $payoutService->approvePayout($this->record, auth()->user());
                    
                    Notification::make()
                        ->title('Payout approved')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['record']);
                })
                ->visible(fn (): bool => 
                    $this->record->status === 'requested' && auth()->user()->can('payouts.approve')
                ),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject Payout Request')
                ->modalDescription(fn (): string => 
                    "Are you sure you want to reject this payout request? The amount will be credited back to the driver's wallet."
                )
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $payoutService = app(PayoutService::class);
                    $payoutService->rejectPayout($this->record, auth()->user(), $data['reason']);
                    
                    Notification::make()
                        ->title('Payout rejected')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['record']);
                })
                ->visible(fn (): bool => 
                    in_array($this->record->status, ['requested', 'approved']) && auth()->user()->can('payouts.reject')
                ),
            Actions\Action::make('mark_paid')
                ->label('Mark Paid')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Payout as Paid')
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
                ->action(function (array $data) {
                    $payoutService = app(PayoutService::class);
                    $payoutService->markPaid($this->record, $data['payout_reference']);
                    
                    Notification::make()
                        ->title('Payout marked as paid')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['record']);
                })
                ->visible(fn (): bool => 
                    in_array($this->record->status, ['approved', 'processing']) && auth()->user()->can('payouts.mark_paid')
                ),
        ];
    }
}
