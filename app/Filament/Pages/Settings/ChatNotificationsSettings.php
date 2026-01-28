<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class ChatNotificationsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string $view = 'filament.pages.settings.chat-notifications-settings';
    protected static ?string $navigationLabel = 'Chat & Notifications';
    protected static ?string $title = 'Chat & Notifications Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 7;

    public function mount(): void
    {
        $this->form->fill([
            // Chat settings
            'chat.enabled' => SystemSetting::get('chat.enabled', true),
            'chat.max_message_length' => SystemSetting::get('chat.max_message_length', 1000),
            'chat.rate_limit_per_minute' => SystemSetting::get('chat.rate_limit_per_minute', 10),
            'chat.allow_after_completion' => SystemSetting::get('chat.allow_after_completion', false),
            'chat.system_messages_enabled' => SystemSetting::get('chat.system_messages_enabled', true),
            
            // Notification settings
            'notifications.enabled' => SystemSetting::get('notifications.enabled', true),
            'notifications.push_enabled' => SystemSetting::get('notifications.push_enabled', true),
            'notifications.db_enabled' => SystemSetting::get('notifications.db_enabled', true),
            'notifications.chat_push' => SystemSetting::get('notifications.chat_push', true),
            'notifications.booking_push' => SystemSetting::get('notifications.booking_push', true),
            'notifications.quiet_hours_enabled' => SystemSetting::get('notifications.quiet_hours_enabled', false),
            'notifications.quiet_hours_start' => SystemSetting::get('notifications.quiet_hours_start', '22:00'),
            'notifications.quiet_hours_end' => SystemSetting::get('notifications.quiet_hours_end', '08:00'),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Chat Settings')
                ->description('Configure chat functionality between riders and drivers')
                ->schema([
                    Toggle::make('chat.enabled')
                        ->label('Enable Chat')
                        ->helperText('Allow riders and drivers to chat within booking conversations')
                        ->default(true),

                    TextInput::make('chat.max_message_length')
                        ->label('Max Message Length')
                        ->numeric()
                        ->default(1000)
                        ->suffix('characters')
                        ->helperText('Maximum number of characters allowed in a single message')
                        ->required(),

                    Select::make('chat.rate_limit_per_minute')
                        ->label('Rate Limit')
                        ->options([
                            5 => '5 messages per minute',
                            10 => '10 messages per minute',
                            15 => '15 messages per minute',
                            20 => '20 messages per minute',
                            30 => '30 messages per minute',
                        ])
                        ->default(10)
                        ->helperText('Maximum number of messages a user can send per minute to prevent spam')
                        ->required(),

                    Toggle::make('chat.allow_after_completion')
                        ->label('Allow Chat After Completion')
                        ->helperText('Allow chat to continue after the ride is completed')
                        ->default(false),

                    Toggle::make('chat.system_messages_enabled')
                        ->label('Enable System Messages')
                        ->helperText('Automatically insert system messages when booking status changes (e.g., "Booking accepted", "Ride completed")')
                        ->default(true),
                ]),

            Section::make('Notification Settings')
                ->description('Configure notification delivery preferences')
                ->schema([
                    Toggle::make('notifications.enabled')
                        ->label('Enable Notifications')
                        ->helperText('Master toggle for all notifications. When disabled, no notifications will be sent.')
                        ->default(true),

                    Toggle::make('notifications.push_enabled')
                        ->label('Enable Push Notifications')
                        ->helperText('Send push notifications via Firebase Cloud Messaging')
                        ->default(true)
                        ->visible(fn ($get) => $get('notifications.enabled')),

                    Toggle::make('notifications.db_enabled')
                        ->label('Save to Database')
                        ->helperText('Store notifications in the database for in-app notification history')
                        ->default(true)
                        ->visible(fn ($get) => $get('notifications.enabled')),

                    Toggle::make('notifications.chat_push')
                        ->label('Chat Push Notifications')
                        ->helperText('Send push notifications for new chat messages')
                        ->default(true)
                        ->visible(fn ($get) => $get('notifications.enabled') && $get('notifications.push_enabled')),

                    Toggle::make('notifications.booking_push')
                        ->label('Booking Push Notifications')
                        ->helperText('Send push notifications for booking status changes (accepted, rejected, cancelled, etc.)')
                        ->default(true)
                        ->visible(fn ($get) => $get('notifications.enabled') && $get('notifications.push_enabled')),

                    Toggle::make('notifications.quiet_hours_enabled')
                        ->label('Enable Quiet Hours')
                        ->helperText('Suppress push notifications during specified hours (database notifications still saved)')
                        ->default(false)
                        ->visible(fn ($get) => $get('notifications.enabled') && $get('notifications.push_enabled')),

                    TimePicker::make('notifications.quiet_hours_start')
                        ->label('Quiet Hours Start')
                        ->default('22:00')
                        ->helperText('Start time for quiet hours (24-hour format). Push notifications will be suppressed during this period.')
                        ->visible(fn ($get) => $get('notifications.enabled') && $get('notifications.push_enabled') && $get('notifications.quiet_hours_enabled'))
                        ->required(),

                    TimePicker::make('notifications.quiet_hours_end')
                        ->label('Quiet Hours End')
                        ->default('08:00')
                        ->helperText('End time for quiet hours (24-hour format). Supports overnight periods (e.g., 22:00 to 08:00).')
                        ->visible(fn ($get) => $get('notifications.enabled') && $get('notifications.push_enabled') && $get('notifications.quiet_hours_enabled'))
                        ->required(),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}

