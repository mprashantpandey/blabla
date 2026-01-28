<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Services\SupportService;
use Filament\Notifications\Notification;
use Filament\Forms;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reply')
                ->label('Reply')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Reply to Support Ticket')
                ->modalDescription(fn (): string => 
                    "Reply to ticket: {$this->record->subject}"
                )
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Message')
                        ->required()
                        ->rows(5)
                        ->maxLength(5000)
                        ->helperText('Your reply will be sent to the user'),
                ])
                ->action(function (array $data) {
                    $supportService = app(SupportService::class);
                    $user = auth()->user();
                    $isAdmin = $user->hasAnyRole(['super_admin', 'city_admin', 'support_staff']);
                    $supportService->addReply($this->record, $user, $data['message'], $isAdmin);
                    
                    Notification::make()
                        ->title('Reply sent')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['record']);
                })
                ->visible(fn (): bool => auth()->user()->can('support.reply')),
            Actions\Action::make('change_status')
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
                        ->default(fn () => $this->record->status),
                ])
                ->action(function (array $data) {
                    $supportService = app(SupportService::class);
                    $supportService->updateStatus($this->record, $data['status']);
                    
                    Notification::make()
                        ->title('Status updated')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['record']);
                })
                ->visible(fn (): bool => auth()->user()->can('support.change_status')),
        ];
    }
}
