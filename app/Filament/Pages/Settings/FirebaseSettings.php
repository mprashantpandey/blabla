<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Crypt;

class FirebaseSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-fire';
    protected static string $view = 'filament.pages.settings.firebase-settings';
    protected static ?string $navigationLabel = 'Push Notifications';
    protected static ?string $title = 'Firebase & Push Notification Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;

    public function mount(): void
    {
        $serviceAccountJson = SystemSetting::get('firebase.service_account_json', '');
        $decrypted = $serviceAccountJson ? (function() use ($serviceAccountJson) {
            try {
                return Crypt::decryptString($serviceAccountJson);
            } catch (\Exception $e) {
                return $serviceAccountJson; // If not encrypted, return as-is
            }
        })() : '';

        $this->form->fill([
            'project_id' => SystemSetting::get('firebase.project_id', ''),
            'service_account_json' => $decrypted,
            'push_enabled' => SystemSetting::get('push.enabled', false),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Push Notification Settings')
                ->description('Enable push notifications to send real-time alerts to users\' mobile devices')
                ->schema([
                    Toggle::make('push_enabled')
                        ->label('Enable Push Notifications')
                        ->helperText('When enabled, notifications are sent to users\' devices. When disabled, notifications are only saved to the database.')
                        ->default(false)
                        ->reactive(),
                ]),
            
            Section::make('Firebase Configuration')
                ->description('Configure Firebase Cloud Messaging (FCM) to send push notifications. Get your credentials from Firebase Console.')
                ->schema([
                    TextInput::make('project_id')
                        ->label('Firebase Project ID')
                        ->placeholder('your-project-id')
                        ->helperText('Your Firebase project ID (found in Firebase Console > Project Settings > General)')
                        ->visible(fn ($get) => $get('push_enabled')),
                    Textarea::make('service_account_json')
                        ->label('Service Account JSON')
                        ->placeholder('{"type":"service_account","project_id":"..."}')
                        ->rows(12)
                        ->helperText('Paste your complete Firebase service account JSON here. This file is downloaded from Firebase Console > Project Settings > Service Accounts. This data will be encrypted for security.')
                        ->visible(fn ($get) => $get('push_enabled'))
                        ->required(fn ($get) => $get('push_enabled')),
                ])
                ->visible(fn ($get) => $get('push_enabled')),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Encrypt service account JSON
        if (!empty($data['service_account_json'])) {
            try {
                // Validate JSON
                $json = json_decode($data['service_account_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Notification::make()
                        ->title('Invalid JSON format')
                        ->body('The service account JSON is not valid. Please check the format and try again.')
                        ->danger()
                        ->send();
                    return;
                }

                $encrypted = Crypt::encryptString($data['service_account_json']);
                SystemSetting::set('firebase.service_account_json', $encrypted, 'text', 'push');
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Encryption failed')
                    ->body('Failed to encrypt service account JSON: ' . $e->getMessage())
                    ->danger()
                    ->send();
                return;
            }
        }

        SystemSetting::set('firebase.project_id', $data['project_id'] ?? '', 'string', 'push');
        SystemSetting::set('push.enabled', $data['push_enabled'] ?? false, 'boolean', 'push');

        SystemSetting::clearCache();

        Notification::make()
            ->title('Push notification settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('testPush')
                ->label('Send Test Push Notification')
                ->icon('heroicon-o-bell')
                ->color('warning')
                ->form([
                    TextInput::make('user_id')
                        ->label('User ID')
                        ->required()
                        ->numeric()
                        ->helperText('Enter the ID of a user who has registered a device to receive the test notification'),
                    TextInput::make('title')
                        ->label('Notification Title')
                        ->required()
                        ->default('Test Notification')
                        ->maxLength(100),
                    Textarea::make('body')
                        ->label('Notification Message')
                        ->required()
                        ->default('This is a test push notification from the admin panel')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    try {
                        $notificationService = app(NotificationService::class);
                        $user = \App\Models\User::find($data['user_id']);
                        
                        if (!$user) {
                            Notification::make()
                                ->title('User not found')
                                ->body('Please enter a valid user ID')
                                ->danger()
                                ->send();
                            return;
                        }

                        $result = $notificationService->sendToUser(
                            $user,
                            $data['title'],
                            $data['body'],
                            ['type' => 'test']
                        );

                        if ($result['push'] || $result['database']) {
                            Notification::make()
                                ->title('Test push notification sent')
                                ->body("Database: " . ($result['database'] ? 'Yes' : 'No') . ", Push: " . ($result['push'] ? 'Yes' : 'No') . ", Devices notified: {$result['devices_notified']}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to send test push')
                                ->body('Please check your Firebase configuration and ensure the user has registered a device')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error sending test push')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn ($get) => $get('push_enabled')),
            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
}
