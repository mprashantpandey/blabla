<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use App\Models\City;

class LocationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static string $view = 'filament.pages.settings.location-settings';
    protected static ?string $navigationLabel = 'Locations';
    protected static ?string $title = 'Location & City Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 5;

    public function mount(): void
    {
        $this->form->fill([
            'require_service_area' => SystemSetting::get('locations.require_service_area', false),
            'default_country' => SystemSetting::get('locations.default_country', 'US'),
            'default_city_slug' => SystemSetting::get('locations.default_city_slug', ''),
            'max_city_distance_km' => SystemSetting::get('locations.max_city_distance_km', 100),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Service Area Rules')
                ->description('Control whether locations must be within defined service areas to be valid')
                ->schema([
                    Toggle::make('require_service_area')
                        ->label('Require Service Area Validation')
                        ->helperText('If enabled, origin and destination locations must be within a city\'s service area for rides to be created. If disabled, any location is allowed.')
                        ->default(false),
                ]),

            Section::make('Default Location Settings')
                ->description('Configure default location preferences for new users')
                ->schema([
                    TextInput::make('default_country')
                        ->label('Default Country Code')
                        ->maxLength(2)
                        ->default('US')
                        ->helperText('Two-letter country code (ISO 3166-1 alpha-2). Examples: US, IN, GB, CA')
                        ->required(),
                    Select::make('default_city_slug')
                        ->label('Default City')
                        ->options(fn () => City::where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($city) => [$city->slug => "{$city->name}, {$city->state}"])
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->helperText('Optional: Select a default city that new users will see when they first open the app. Leave empty to let users choose.'),
                    TextInput::make('max_city_distance_km')
                        ->label('Maximum City Resolution Distance')
                        ->numeric()
                        ->default(100)
                        ->suffix('km')
                        ->minValue(1)
                        ->maxValue(1000)
                        ->helperText('When a user provides coordinates, the system will search for cities within this distance. If no city is found, the user must manually select a city.')
                        ->required(),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            SystemSetting::set("locations.{$key}", $value, $type, 'locations');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Location settings saved successfully')
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
