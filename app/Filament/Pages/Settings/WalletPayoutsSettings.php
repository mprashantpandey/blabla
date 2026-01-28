<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class WalletPayoutsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static string $view = 'filament.pages.settings.wallet-payouts-settings';
    protected static ?string $navigationLabel = 'Wallet & Payouts';
    protected static ?string $title = 'Wallet & Payouts Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 9;

    public function mount(): void
    {
        $this->form->fill([
            // Wallet settings
            'wallet.enabled' => SystemSetting::get('wallet.enabled', true),
            'wallet.min_payout_amount' => SystemSetting::get('wallet.min_payout_amount', 100),
            'wallet.allow_negative_balance' => SystemSetting::get('wallet.allow_negative_balance', false),
            'wallet.show_wallet_to_driver' => SystemSetting::get('wallet.show_wallet_to_driver', true),
            
            // Payouts settings
            'payouts.enabled' => SystemSetting::get('payouts.enabled', true),
            'payouts.methods' => SystemSetting::get('payouts.methods', ['bank', 'manual']),
            'payouts.auto_approve' => SystemSetting::get('payouts.auto_approve', false),
            'payouts.processing_time_text' => SystemSetting::get('payouts.processing_time_text', '2–3 business days'),
            'payouts.require_bank_details' => SystemSetting::get('payouts.require_bank_details', true),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Wallet Settings')
                ->description('Configure driver wallet functionality')
                ->schema([
                    Toggle::make('wallet.enabled')
                        ->label('Enable Wallet')
                        ->helperText('Enable wallet system for drivers to track earnings and request payouts')
                        ->default(true),

                    TextInput::make('wallet.min_payout_amount')
                        ->label('Minimum Payout Amount')
                        ->numeric()
                        ->default(100)
                        ->suffix('currency')
                        ->helperText('Minimum amount drivers must have in wallet before requesting a payout')
                        ->required()
                        ->visible(fn ($get) => $get('wallet.enabled')),

                    Toggle::make('wallet.allow_negative_balance')
                        ->label('Allow Negative Balance')
                        ->helperText('Allow wallet balance to go negative (e.g., for adjustments or refunds). Use with caution.')
                        ->default(false)
                        ->visible(fn ($get) => $get('wallet.enabled')),

                    Toggle::make('wallet.show_wallet_to_driver')
                        ->label('Show Wallet to Driver')
                        ->helperText('Allow drivers to view their wallet balance and transaction history in the app')
                        ->default(true)
                        ->visible(fn ($get) => $get('wallet.enabled')),
                ]),

            Section::make('Payout Settings')
                ->description('Configure payout request and processing')
                ->schema([
                    Toggle::make('payouts.enabled')
                        ->label('Enable Payouts')
                        ->helperText('Allow drivers to request payouts from their wallet balance')
                        ->default(true),

                    Select::make('payouts.methods')
                        ->label('Allowed Payout Methods')
                        ->options([
                            'bank' => 'Bank Transfer',
                            'razorpay' => 'Razorpay',
                            'stripe' => 'Stripe',
                            'cash' => 'Cash',
                            'manual' => 'Manual Processing',
                        ])
                        ->multiple()
                        ->default(['bank', 'manual'])
                        ->helperText('Select which payout methods drivers can use. Multiple methods can be enabled.')
                        ->required()
                        ->visible(fn ($get) => $get('payouts.enabled')),

                    Toggle::make('payouts.auto_approve')
                        ->label('Auto-Approve Payouts')
                        ->helperText('Automatically approve payout requests (use with caution - funds are debited immediately)')
                        ->default(false)
                        ->visible(fn ($get) => $get('payouts.enabled')),

                    Textarea::make('payouts.processing_time_text')
                        ->label('Processing Time Information')
                        ->default('2–3 business days')
                        ->rows(2)
                        ->helperText('Text shown to drivers about payout processing time (e.g., "2–3 business days")')
                        ->visible(fn ($get) => $get('payouts.enabled'))
                        ->required(),

                    Toggle::make('payouts.require_bank_details')
                        ->label('Require Bank Details')
                        ->helperText('Require drivers to provide bank account details before requesting payouts')
                        ->default(true)
                        ->visible(fn ($get) => $get('payouts.enabled')),
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

