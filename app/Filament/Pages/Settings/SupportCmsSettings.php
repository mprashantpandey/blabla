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

class SupportCmsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';
    protected static string $view = 'filament.pages.settings.support-cms-settings';
    protected static ?string $navigationLabel = 'Support & CMS';
    protected static ?string $title = 'Support & CMS Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;

    public function mount(): void
    {
        $this->form->fill([
            'support.enabled' => SystemSetting::get('support.enabled', true),
            'support.allow_booking_reference' => SystemSetting::get('support.allow_booking_reference', true),
            'support.default_priority' => SystemSetting::get('support.default_priority', 'medium'),
            'support.auto_assign_city' => SystemSetting::get('support.auto_assign_city', true),
            'cms.enabled' => SystemSetting::get('cms.enabled', true),
            'cms.footer_limit' => SystemSetting::get('cms.footer_limit', 5),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Support Settings')
                ->description('Configure the support ticket system')
                ->schema([
                    Toggle::make('support.enabled')
                        ->label('Enable Support System')
                        ->helperText('Allow users to create support tickets')
                        ->default(true),
                    Toggle::make('support.allow_booking_reference')
                        ->label('Allow Booking Reference')
                        ->helperText('Allow users to link support tickets to bookings')
                        ->default(true)
                        ->visible(fn ($get) => $get('support.enabled')),
                    Select::make('support.default_priority')
                        ->label('Default Priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                        ])
                        ->default('medium')
                        ->helperText('Default priority for new support tickets')
                        ->required()
                        ->visible(fn ($get) => $get('support.enabled')),
                    Toggle::make('support.auto_assign_city')
                        ->label('Auto-Assign City')
                        ->helperText('Automatically assign ticket to user\'s city')
                        ->default(true)
                        ->visible(fn ($get) => $get('support.enabled')),
                ]),
            Section::make('CMS Settings')
                ->description('Configure content management system')
                ->schema([
                    Toggle::make('cms.enabled')
                        ->label('Enable CMS')
                        ->helperText('Enable content management system for static pages')
                        ->default(true),
                    TextInput::make('cms.footer_limit')
                        ->label('Footer Pages Limit')
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(10)
                        ->helperText('Maximum number of pages to show in footer')
                        ->required()
                        ->visible(fn ($get) => $get('cms.enabled')),
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
