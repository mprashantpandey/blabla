<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class RideSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static string $view = 'filament.pages.settings.ride-settings';
    protected static ?string $navigationLabel = 'Rides';
    protected static ?string $title = 'Ride Configuration';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 7;

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => SystemSetting::get('rides.enabled', true),
            'allow_draft' => SystemSetting::get('rides.allow_draft', true),
            'min_hours_before_departure' => SystemSetting::get('rides.min_hours_before_departure', 1),
            'max_days_in_future' => SystemSetting::get('rides.max_days_in_future', 60),
            'max_waypoints' => SystemSetting::get('rides.max_waypoints', 3),
            'max_seats' => SystemSetting::get('rides.max_seats', 8),
            'min_price' => SystemSetting::get('rides.min_price', 0),
            'max_price' => SystemSetting::get('rides.max_price', 9999),
            'currency_inherit_from_city' => SystemSetting::get('rides.currency_inherit_from_city', true),
            'default_search_radius_km' => SystemSetting::get('rides.default_search_radius_km', 30),
            'require_serviceable_points' => SystemSetting::get('rides.require_serviceable_points', false),
            'auto_cancel_on_departure_past' => SystemSetting::get('rides.auto_cancel_on_departure_past', true),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('General Settings')
                ->description('Basic ride functionality controls')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable Rides')
                        ->helperText('Allow drivers to create and publish rides. When disabled, the ride creation feature is hidden.')
                        ->default(true),
                    Toggle::make('allow_draft')
                        ->label('Allow Draft Rides')
                        ->helperText('If enabled, drivers can save rides as drafts and publish them later. If disabled, rides must be published immediately.')
                        ->default(true),
                ])->columns(2),

            Section::make('Time Restrictions')
                ->description('Control when drivers can schedule rides')
                ->schema([
                    TextInput::make('min_hours_before_departure')
                        ->label('Minimum Advance Notice')
                        ->numeric()
                        ->default(1)
                        ->suffix('hours')
                        ->minValue(0)
                        ->maxValue(168)
                        ->helperText('Minimum hours before departure time when creating a ride (e.g., 1 = rides must be at least 1 hour in the future)')
                        ->required(),
                    TextInput::make('max_days_in_future')
                        ->label('Maximum Days Ahead')
                        ->numeric()
                        ->default(60)
                        ->suffix('days')
                        ->minValue(1)
                        ->maxValue(365)
                        ->helperText('Maximum number of days in the future that a ride can be scheduled (e.g., 60 = rides can be up to 60 days ahead)')
                        ->required(),
                ])->columns(2),

            Section::make('Ride Configuration Limits')
                ->description('Set limits on ride features')
                ->schema([
                    TextInput::make('max_waypoints')
                        ->label('Maximum Waypoints')
                        ->numeric()
                        ->default(3)
                        ->minValue(0)
                        ->maxValue(10)
                        ->helperText('Maximum number of intermediate stops (waypoints) a driver can add to a ride route')
                        ->required(),
                    TextInput::make('max_seats')
                        ->label('Maximum Seats per Ride')
                        ->numeric()
                        ->default(8)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Maximum number of seats a driver can offer in a single ride')
                        ->required(),
                ])->columns(2),

            Section::make('Pricing Settings')
                ->description('Control price limits and currency behavior')
                ->schema([
                    TextInput::make('min_price')
                        ->label('Minimum Price per Seat')
                        ->numeric()
                        ->default(0)
                        ->prefix('$')
                        ->minValue(0)
                        ->maxValue(9999)
                        ->helperText('Minimum price a driver can charge per seat (prevents free or negative prices)')
                        ->required(),
                    TextInput::make('max_price')
                        ->label('Maximum Price per Seat')
                        ->numeric()
                        ->default(9999)
                        ->prefix('$')
                        ->minValue(1)
                        ->maxValue(99999)
                        ->helperText('Maximum price a driver can charge per seat (prevents unreasonably high prices)')
                        ->required(),
                    Toggle::make('currency_inherit_from_city')
                        ->label('Use City Currency by Default')
                        ->helperText('If enabled, the ride currency automatically matches the city\'s currency. If disabled, drivers can choose any currency.')
                        ->default(true),
                ])->columns(2),

            Section::make('Search & Serviceability')
                ->description('Configure how riders search for rides and location validation')
                ->schema([
                    TextInput::make('default_search_radius_km')
                        ->label('Default Search Radius')
                        ->numeric()
                        ->default(30)
                        ->suffix('km')
                        ->minValue(1)
                        ->maxValue(500)
                        ->helperText('Default distance radius when riders search for rides near their location (in kilometers)')
                        ->required(),
                    Toggle::make('require_serviceable_points')
                        ->label('Require Service Area Validation')
                        ->helperText('If enabled, origin and destination must be within the city\'s service area. If disabled, any location is allowed.')
                        ->default(false),
                ])->columns(2),

            Section::make('Automation')
                ->description('Automatic ride management features')
                ->schema([
                    Toggle::make('auto_cancel_on_departure_past')
                        ->label('Auto-Cancel Past Rides')
                        ->helperText('Automatically cancel published rides when the departure time has passed. This keeps the ride list clean and prevents confusion.')
                        ->default(true),
                ]),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? (is_float($value) ? 'decimal' : 'integer') : 'string');
            SystemSetting::set("rides.{$key}", $value, $type, 'rides');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Ride settings saved successfully')
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
