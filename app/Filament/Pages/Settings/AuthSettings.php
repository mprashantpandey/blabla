<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Accordion;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use App\Services\SmsService;

class AuthSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string $view = 'filament.pages.settings.auth-settings';
    protected static ?string $navigationLabel = 'Authentication';
    protected static ?string $title = 'Authentication & Login Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;

    public function mount(): void
    {
        $this->form->fill([
            'enable_email_password' => SystemSetting::get('auth.enable_email_password', true),
            'enable_phone_otp' => SystemSetting::get('auth.enable_phone_otp', true),
            'enable_social_google' => SystemSetting::get('auth.enable_social_google', false),
            'enable_social_apple' => SystemSetting::get('auth.enable_social_apple', false),
            'require_email_verification' => SystemSetting::get('auth.require_email_verification', false),
            'require_phone_verification' => SystemSetting::get('auth.require_phone_verification', false),
            'otp_provider' => SystemSetting::get('auth.otp_provider', 'firebase'),
            'custom_sms_provider' => SystemSetting::get('auth.custom_sms_provider', 'msg91'),
            'otp_ttl_seconds' => SystemSetting::get('auth.otp_ttl_seconds', 300),
            'otp_max_attempts' => SystemSetting::get('auth.otp_max_attempts', 5),
            'social_auto_register' => SystemSetting::get('auth.social_auto_register', true),
            'google_client_ids' => SystemSetting::get('auth.google_client_ids', ''),
            'apple_client_id' => SystemSetting::get('auth.apple_client_id', ''),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Login Methods')
                ->description('Choose which login methods users can use to access the app')
                ->schema([
                    Toggle::make('enable_email_password')
                        ->label('Email & Password Login')
                        ->helperText('Allow users to register and login using their email address and password')
                        ->default(true),
                    Toggle::make('enable_phone_otp')
                        ->label('Phone OTP Login')
                        ->helperText('Allow users to login using their phone number with a one-time password sent via SMS')
                        ->default(true),
                    Toggle::make('enable_social_google')
                        ->label('Google Sign-In')
                        ->helperText('Enable "Sign in with Google" option for users')
                        ->default(false),
                    Toggle::make('enable_social_apple')
                        ->label('Apple Sign-In')
                        ->helperText('Enable "Sign in with Apple" option for iOS users')
                        ->default(false),
                ])->columns(2),
            
            Section::make('Verification Requirements')
                ->description('Control whether users must verify their email or phone before using the app')
                ->schema([
                    Toggle::make('require_email_verification')
                        ->label('Require Email Verification')
                        ->helperText('Users must verify their email address before they can use the app')
                        ->default(false),
                    Toggle::make('require_phone_verification')
                        ->label('Require Phone Verification')
                        ->helperText('Users must verify their phone number before they can use the app')
                        ->default(false),
                ])->columns(2),
            
            Section::make('OTP (One-Time Password) Settings')
                ->description('Configure how phone verification codes are sent to users')
                ->schema([
                    Select::make('otp_provider')
                        ->label('OTP Delivery Method')
                        ->options([
                            'firebase' => 'Firebase (Recommended)',
                            'custom_sms' => 'Custom SMS Provider',
                        ])
                        ->default('firebase')
                        ->helperText('Firebase: Uses Firebase Auth for OTP. Custom SMS: Uses your SMS provider.')
                        ->required()
                        ->reactive(),
                    Select::make('custom_sms_provider')
                        ->label('SMS Provider')
                        ->options([
                            'msg91' => 'MSG91',
                            'twilio' => 'Twilio',
                            'generic_http' => 'Generic HTTP API',
                        ])
                        ->visible(fn ($get) => $get('otp_provider') === 'custom_sms')
                        ->helperText('Select your SMS service provider. Configure credentials in SMS Settings.')
                        ->required(fn ($get) => $get('otp_provider') === 'custom_sms'),
                    TextInput::make('otp_ttl_seconds')
                        ->label('OTP Expiry Time')
                        ->numeric()
                        ->default(300)
                        ->suffix('seconds')
                        ->minValue(60)
                        ->maxValue(3600)
                        ->helperText('How long the OTP code remains valid (60-3600 seconds)')
                        ->required(),
                    TextInput::make('otp_max_attempts')
                        ->label('Maximum Verification Attempts')
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(10)
                        ->helperText('Maximum number of times a user can try to verify the OTP code')
                        ->required(),
                ])->columns(2),
            
            Section::make('Social Login Settings')
                ->description('Configure Google and Apple sign-in options')
                ->schema([
                    Toggle::make('social_auto_register')
                        ->label('Auto-Create Account on Social Login')
                        ->helperText('If enabled, a new account is automatically created when a user signs in with Google or Apple for the first time')
                        ->default(true),
                    TextInput::make('google_client_ids')
                        ->label('Google Client IDs')
                        ->placeholder('client-id-1,client-id-2')
                        ->helperText('Optional: Comma-separated list of Google OAuth client IDs for additional security validation')
                        ->visible(fn ($get) => $get('enable_social_google')),
                    TextInput::make('apple_client_id')
                        ->label('Apple Service ID')
                        ->placeholder('com.example.service')
                        ->helperText('Optional: Your Apple Sign-In service identifier for additional security validation')
                        ->visible(fn ($get) => $get('enable_social_apple')),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            SystemSetting::set("auth.{$key}", $value, $type, 'auth');
        }
        
        SystemSetting::clearCache();

        Notification::make()
            ->title('Authentication settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('testSms')
                ->label('Test SMS OTP')
                ->icon('heroicon-o-phone')
                ->color('warning')
                ->form([
                    TextInput::make('phone')
                        ->label('Phone Number')
                        ->required()
                        ->tel()
                        ->helperText('Enter a phone number to receive a test OTP code'),
                ])
                ->action(function (array $data) {
                    try {
                        $smsService = app(SmsService::class);
                        $sent = $smsService->test($data['phone']);
                        
                        if ($sent) {
                            Notification::make()
                                ->title('Test SMS sent successfully')
                                ->body('Check the phone number for the OTP code')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to send test SMS')
                                ->body('Please check your SMS provider configuration')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error sending test SMS')
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
