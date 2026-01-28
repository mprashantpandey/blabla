<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class BookingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static string $view = 'filament.pages.settings.booking-settings';
    protected static ?string $navigationLabel = 'Bookings';
    protected static ?string $title = 'Booking Configuration';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => SystemSetting::get('bookings.enabled', true),
            'require_driver_acceptance_default' => SystemSetting::get('bookings.require_driver_acceptance_default', true),
            'seat_hold_minutes' => SystemSetting::get('bookings.seat_hold_minutes', 10),
            'allow_cancellation' => SystemSetting::get('bookings.allow_cancellation', true),
            'cancellation_deadline_hours' => SystemSetting::get('bookings.cancellation_deadline_hours', 3),
            'refund_policy' => SystemSetting::get('bookings.refund_policy', 'none'),
            'refund_partial_percent' => SystemSetting::get('bookings.refund_partial_percent', 50),
            'max_active_requests_per_user' => SystemSetting::get('bookings.max_active_requests_per_user', 5),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('General Settings')
                ->description('Basic booking functionality controls')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable Bookings')
                        ->helperText('Allow riders to request seats on published rides. When disabled, the booking feature is hidden from the app.')
                        ->default(true),
                ]),

            Section::make('Acceptance Settings')
                ->description('Control whether drivers must manually accept booking requests')
                ->schema([
                    Toggle::make('require_driver_acceptance_default')
                        ->label('Require Driver Acceptance by Default')
                        ->helperText('If enabled, drivers must manually accept each booking request. If disabled and the ride allows instant booking, bookings are automatically accepted.')
                        ->default(true),
                    Select::make('seat_hold_minutes')
                        ->label('Seat Hold Duration')
                        ->options([
                            5 => '5 minutes',
                            10 => '10 minutes',
                            15 => '15 minutes',
                            20 => '20 minutes',
                        ])
                        ->default(10)
                        ->required()
                        ->helperText('How long seats are reserved when a booking is requested. After this time, if payment is not completed, seats are released automatically.'),
                ])->columns(2),

            Section::make('Cancellation Policy')
                ->description('Configure when and how bookings can be cancelled')
                ->schema([
                    Toggle::make('allow_cancellation')
                        ->label('Allow Cancellations')
                        ->helperText('Allow riders and drivers to cancel bookings. When disabled, cancellations are not allowed.')
                        ->default(true)
                        ->reactive(),
                    Select::make('cancellation_deadline_hours')
                        ->label('Cancellation Deadline')
                        ->options([
                            1 => '1 hour before departure',
                            2 => '2 hours before departure',
                            3 => '3 hours before departure',
                            6 => '6 hours before departure',
                            12 => '12 hours before departure',
                            24 => '24 hours before departure',
                        ])
                        ->default(3)
                        ->required()
                        ->visible(fn (callable $get) => $get('allow_cancellation'))
                        ->helperText('Bookings cannot be cancelled after this deadline. This gives drivers time to find replacement passengers.'),
                ])->columns(2),

            Section::make('Refund Policy')
                ->description('Configure refund rules for cancelled bookings with online payments (Razorpay/Stripe). Cash payments are not refunded.')
                ->schema([
                    Select::make('refund_policy')
                        ->label('Refund Policy')
                        ->options([
                            'none' => 'No Refund',
                            'full' => 'Full Refund',
                            'partial' => 'Partial Refund',
                        ])
                        ->default('none')
                        ->required()
                        ->reactive()
                        ->helperText('Refund policy for cancelled bookings with online payments. "No Refund" means riders keep their money. "Full Refund" returns 100% of the payment. "Partial Refund" returns a percentage.'),
                    TextInput::make('refund_partial_percent')
                        ->label('Partial Refund Percentage')
                        ->numeric()
                        ->default(50)
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100)
                        ->visible(fn (callable $get) => $get('refund_policy') === 'partial')
                        ->required(fn (callable $get) => $get('refund_policy') === 'partial')
                        ->helperText('Percentage of the booking amount to refund when a booking is cancelled (0-100%). For example, 50% means riders get half their money back.'),
                ])->columns(2),

            Section::make('User Limits')
                ->description('Control how many active bookings a user can have')
                ->schema([
                    Select::make('max_active_requests_per_user')
                        ->label('Maximum Active Booking Requests')
                        ->options([
                            1 => '1 booking',
                            2 => '2 bookings',
                            3 => '3 bookings',
                            5 => '5 bookings',
                            10 => '10 bookings',
                        ])
                        ->default(5)
                        ->required()
                        ->helperText('Maximum number of active booking requests (requested, accepted, or confirmed) a single user can have at the same time. This prevents abuse and ensures fair access.'),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            SystemSetting::set("bookings.{$key}", $value, $type, 'bookings');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Booking settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
}
