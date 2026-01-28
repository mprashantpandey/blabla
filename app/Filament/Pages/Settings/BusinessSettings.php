<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class BusinessSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string $view = 'filament.pages.settings.business-settings';
    protected static ?string $navigationLabel = 'Business';
    protected static ?string $title = 'Business & Commission Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;

    public function mount(): void
    {
        $this->form->fill([
            'commission_type' => SystemSetting::get('business.commission_type', 'percent'),
            'commission_value' => SystemSetting::get('business.commission_value', 10),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Platform Commission')
                ->description('Configure how much commission the platform takes from each booking. This is deducted from the driver\'s payout, not added to the rider\'s payment.')
                ->schema([
                    Select::make('commission_type')
                        ->label('Commission Type')
                        ->options([
                            'percent' => 'Percentage (%)',
                            'fixed' => 'Fixed Amount',
                        ])
                        ->default('percent')
                        ->required()
                        ->reactive()
                        ->helperText('Percentage: Commission is a percentage of the booking amount. Fixed: Commission is a fixed amount per booking.'),
                    TextInput::make('commission_value')
                        ->label(fn ($get) => $get('commission_type') === 'percent' ? 'Commission Percentage' : 'Commission Amount')
                        ->numeric()
                        ->default(10)
                        ->suffix(fn ($get) => $get('commission_type') === 'percent' ? '%' : null)
                        ->prefix(fn ($get) => $get('commission_type') === 'fixed' ? '$' : null)
                        ->minValue(0)
                        ->maxValue(fn ($get) => $get('commission_type') === 'percent' ? 100 : 999999)
                        ->helperText(fn ($get) => $get('commission_type') === 'percent' 
                            ? 'Percentage of booking amount taken as commission (e.g., 10 = 10% commission). If a booking is $100, you take $10.'
                            : 'Fixed amount taken as commission per booking (e.g., 5 = $5 commission per booking regardless of booking amount).')
                        ->required(),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SystemSetting::set('business.commission_type', $data['commission_type'], 'string', 'business');
        SystemSetting::set('business.commission_value', $data['commission_value'], 'integer', 'business');

        SystemSetting::clearCache();

        Notification::make()
            ->title('Business settings saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
}
