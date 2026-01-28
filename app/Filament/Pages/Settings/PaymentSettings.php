<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Razorpay\Api\Api as RazorpayApi;
use Stripe\Stripe as StripeSDK;
use Stripe\PaymentIntent;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.pages.settings.payment-settings';
    protected static ?string $navigationLabel = 'Payments';
    protected static ?string $title = 'Payment Gateway Configuration';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 9;

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => SystemSetting::get('payments.enabled', true),
            'method_cash_enabled' => SystemSetting::get('payments.method_cash_enabled', true),
            'razorpay_enabled' => SystemSetting::get('payments.razorpay_enabled', false),
            'razorpay_key_id' => $this->decryptIfNeeded(SystemSetting::get('payments.razorpay_key_id')),
            'razorpay_key_secret' => $this->decryptIfNeeded(SystemSetting::get('payments.razorpay_key_secret')),
            'stripe_enabled' => SystemSetting::get('payments.stripe_enabled', false),
            'stripe_publishable_key' => $this->decryptIfNeeded(SystemSetting::get('payments.stripe_publishable_key')),
            'stripe_secret_key' => $this->decryptIfNeeded(SystemSetting::get('payments.stripe_secret_key')),
            'currency_default' => SystemSetting::get('payments.currency_default', 'INR'),
            'capture_mode' => SystemSetting::get('payments.capture_mode', 'auto'),
            'test_mode' => SystemSetting::get('payments.test_mode', false),
        ]);
    }

    protected function decryptIfNeeded(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    protected function getFormSchema(): array
    {
        $currencies = [
            'INR' => 'INR - Indian Rupee',
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'CAD' => 'CAD - Canadian Dollar',
            'AUD' => 'AUD - Australian Dollar',
            'SGD' => 'SGD - Singapore Dollar',
            'AED' => 'AED - UAE Dirham',
            'SAR' => 'SAR - Saudi Riyal',
            'JPY' => 'JPY - Japanese Yen',
            'CNY' => 'CNY - Chinese Yuan',
            'PKR' => 'PKR - Pakistani Rupee',
            'BDT' => 'BDT - Bangladeshi Taka',
            'NZD' => 'NZD - New Zealand Dollar',
            'ZAR' => 'ZAR - South African Rand',
        ];

        return [
            Section::make('General Settings')
                ->description('Basic payment functionality controls')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable Payments')
                        ->helperText('Allow riders to pay for bookings. When disabled, only cash payments are allowed.')
                        ->default(true),
                    Toggle::make('test_mode')
                        ->label('Test Mode')
                        ->helperText('Use test/sandbox credentials for payment gateways. Enable this during development and testing. Disable for production.')
                        ->default(false),
                ])->columns(2),

            Section::make('Payment Methods')
                ->description('Enable or disable specific payment methods')
                ->schema([
                    Toggle::make('method_cash_enabled')
                        ->label('Enable Cash Payments')
                        ->helperText('Allow riders to pay with cash. Cash payments are collected directly from the driver.')
                        ->default(true),
                    Toggle::make('razorpay_enabled')
                        ->label('Enable Razorpay')
                        ->helperText('Enable Razorpay payment gateway for online payments. Popular in India.')
                        ->default(false)
                        ->reactive(),
                    Toggle::make('stripe_enabled')
                        ->label('Enable Stripe')
                        ->helperText('Enable Stripe payment gateway for online payments. Popular worldwide.')
                        ->default(false)
                        ->reactive(),
                ])->columns(3),

            Section::make('Razorpay Configuration')
                ->description('Configure Razorpay payment gateway. Get your credentials from Razorpay Dashboard > Settings > API Keys.')
                ->schema([
                    TextInput::make('razorpay_key_id')
                        ->label('Razorpay Key ID')
                        ->password()
                        ->revealable()
                        ->visible(fn (callable $get) => $get('razorpay_enabled'))
                        ->helperText('Your Razorpay API Key ID (found in Razorpay Dashboard > Settings > API Keys)')
                        ->required(fn (callable $get) => $get('razorpay_enabled')),
                    TextInput::make('razorpay_key_secret')
                        ->label('Razorpay Key Secret')
                        ->password()
                        ->revealable()
                        ->visible(fn (callable $get) => $get('razorpay_enabled'))
                        ->helperText('Your Razorpay API Key Secret (found in Razorpay Dashboard > Settings > API Keys). This will be encrypted for security.')
                        ->required(fn (callable $get) => $get('razorpay_enabled')),
                ])
                ->visible(fn (callable $get) => $get('razorpay_enabled'))
                ->columns(2),

            Section::make('Stripe Configuration')
                ->description('Configure Stripe payment gateway. Get your credentials from Stripe Dashboard > Developers > API Keys.')
                ->schema([
                    TextInput::make('stripe_publishable_key')
                        ->label('Stripe Publishable Key')
                        ->password()
                        ->revealable()
                        ->visible(fn (callable $get) => $get('stripe_enabled'))
                        ->helperText('Your Stripe Publishable Key (starts with pk_test_ or pk_live_). Found in Stripe Dashboard > Developers > API Keys.')
                        ->required(fn (callable $get) => $get('stripe_enabled')),
                    TextInput::make('stripe_secret_key')
                        ->label('Stripe Secret Key')
                        ->password()
                        ->revealable()
                        ->visible(fn (callable $get) => $get('stripe_enabled'))
                        ->helperText('Your Stripe Secret Key (starts with sk_test_ or sk_live_). Found in Stripe Dashboard > Developers > API Keys. This will be encrypted for security.')
                        ->required(fn (callable $get) => $get('stripe_enabled')),
                ])
                ->visible(fn (callable $get) => $get('stripe_enabled'))
                ->columns(2),

            Section::make('Payment Options')
                ->description('Configure default currency and payment capture behavior')
                ->schema([
                    Select::make('currency_default')
                        ->label('Default Currency')
                        ->options($currencies)
                        ->searchable()
                        ->default('INR')
                        ->helperText('Default currency for payments. This can be overridden per city or per ride.')
                        ->required(),
                    Select::make('capture_mode')
                        ->label('Payment Capture Mode')
                        ->options([
                            'auto' => 'Auto Capture (Recommended)',
                            'manual' => 'Manual Capture',
                        ])
                        ->default('auto')
                        ->helperText('Auto Capture: Payment is captured immediately when the rider pays. Manual Capture: Requires admin approval before capturing payment.')
                        ->required(),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Encrypt sensitive fields
        if (isset($data['razorpay_key_secret']) && $data['razorpay_key_secret']) {
            $data['razorpay_key_secret'] = Crypt::encryptString($data['razorpay_key_secret']);
        }
        if (isset($data['stripe_secret_key']) && $data['stripe_secret_key']) {
            $data['stripe_secret_key'] = Crypt::encryptString($data['stripe_secret_key']);
        }
        if (isset($data['stripe_publishable_key']) && $data['stripe_publishable_key']) {
            $data['stripe_publishable_key'] = Crypt::encryptString($data['stripe_publishable_key']);
        }

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            SystemSetting::set("payments.{$key}", $value, $type, 'payments');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Payment settings saved successfully')
            ->success()
            ->send();
    }

    public function testRazorpay(): void
    {
        $data = $this->form->getState();
        $keyId = $data['razorpay_key_id'] ?? null;
        $keySecret = $data['razorpay_key_secret'] ?? null;

        if (!$keyId || !$keySecret) {
            Notification::make()
                ->title('Razorpay credentials not provided')
                ->body('Please enter both Key ID and Key Secret before testing')
                ->danger()
                ->send();
            return;
        }

        try {
            $razorpay = new RazorpayApi($keyId, $keySecret);
            $razorpay->order->all(['count' => 1]); // Test API call

            Notification::make()
                ->title('Razorpay credentials are valid')
                ->body('Successfully connected to Razorpay API')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Razorpay test failed')
                ->body('Could not connect to Razorpay: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testStripe(): void
    {
        $data = $this->form->getState();
        $secretKey = $data['stripe_secret_key'] ?? null;

        if (!$secretKey) {
            Notification::make()
                ->title('Stripe secret key not provided')
                ->body('Please enter your Stripe Secret Key before testing')
                ->danger()
                ->send();
            return;
        }

        try {
            StripeSDK::setApiKey($secretKey);
            PaymentIntent::all(['limit' => 1]); // Test API call

            Notification::make()
                ->title('Stripe credentials are valid')
                ->body('Successfully connected to Stripe API')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Stripe test failed')
                ->body('Could not connect to Stripe: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('testRazorpay')
                ->label('Test Razorpay Connection')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->action('testRazorpay')
                ->visible(fn (callable $get) => $get('razorpay_enabled')),
            Action::make('testStripe')
                ->label('Test Stripe Connection')
                ->icon('heroicon-o-check-circle')
                ->color('warning')
                ->action('testStripe')
                ->visible(fn (callable $get) => $get('stripe_enabled')),
            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
}
