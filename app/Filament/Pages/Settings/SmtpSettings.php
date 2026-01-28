<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use App\Services\MailService;

class SmtpSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static string $view = 'filament.pages.settings.smtp-settings';
    protected static ?string $navigationLabel = 'Email (SMTP)';
    protected static ?string $title = 'Email Server Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    public function mount(): void
    {
        $this->form->fill([
            'host' => SystemSetting::get('smtp.host', ''),
            'port' => SystemSetting::get('smtp.port', 587),
            'username' => SystemSetting::get('smtp.username', ''),
            'password' => SystemSetting::get('smtp.password', ''),
            'encryption' => SystemSetting::get('smtp.encryption', 'tls'),
            'from_email' => SystemSetting::get('smtp.from_email', ''),
            'from_name' => SystemSetting::get('smtp.from_name', 'BlaBla'),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('SMTP Server Configuration')
                ->description('Configure your email server settings. Common providers: Gmail (smtp.gmail.com:587), Outlook (smtp-mail.outlook.com:587), SendGrid (smtp.sendgrid.net:587)')
                ->schema([
                    TextInput::make('host')
                        ->label('SMTP Server Address')
                        ->required()
                        ->placeholder('smtp.gmail.com')
                        ->helperText('The address of your email server (e.g., smtp.gmail.com for Gmail)'),
                    TextInput::make('port')
                        ->label('SMTP Port')
                        ->numeric()
                        ->default(587)
                        ->required()
                        ->helperText('Port 587 (TLS) or 465 (SSL) are most common. Port 25 is usually blocked by ISPs.'),
                    Select::make('encryption')
                        ->label('Encryption Type')
                        ->options([
                            'tls' => 'TLS (Recommended)',
                            'ssl' => 'SSL',
                            'none' => 'None (Not Recommended)',
                        ])
                        ->default('tls')
                        ->required()
                        ->helperText('TLS is recommended for most modern email servers. Use SSL only if your server requires it.'),
                    TextInput::make('username')
                        ->label('SMTP Username')
                        ->required()
                        ->email()
                        ->helperText('Your email address or SMTP username'),
                    TextInput::make('password')
                        ->label('SMTP Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Your email password or app-specific password (for Gmail, use an App Password)'),
                ])->columns(2),
            
            Section::make('Email From Settings')
                ->description('Configure the sender information for emails sent from the system')
                ->schema([
                    TextInput::make('from_email')
                        ->label('From Email Address')
                        ->email()
                        ->required()
                        ->helperText('The email address that will appear as the sender (usually same as SMTP username)'),
                    TextInput::make('from_name')
                        ->label('From Name')
                        ->default('BlaBla')
                        ->required()
                        ->helperText('The display name that will appear as the sender (e.g., "BlaBla" or "Your Company Name")'),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = is_numeric($value) ? 'integer' : 'string';
            SystemSetting::set("smtp.{$key}", $value, $type, 'email');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Email settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->form([
                    TextInput::make('email')
                        ->label('Test Email Address')
                        ->email()
                        ->required()
                        ->helperText('Enter an email address to receive a test email'),
                ])
                ->action(function (array $data) {
                    try {
                        $mailService = app(MailService::class);
                        $sent = $mailService->test($data['email']);
                        
                        if ($sent) {
                            Notification::make()
                                ->title('Test email sent successfully')
                                ->body('Check the inbox and spam folder for the test email')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to send test email')
                                ->body('Please check your SMTP configuration and try again')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error sending test email')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
}
