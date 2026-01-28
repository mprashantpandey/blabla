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

class RatingsTrustSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static string $view = 'filament.pages.settings.ratings-trust-settings';
    protected static ?string $navigationLabel = 'Ratings & Trust';
    protected static ?string $title = 'Ratings & Trust Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;

    public function mount(): void
    {
        $this->form->fill([
            // Ratings settings
            'ratings.enabled' => SystemSetting::get('ratings.enabled', true),
            'ratings.min_value' => SystemSetting::get('ratings.min_value', 1),
            'ratings.max_value' => SystemSetting::get('ratings.max_value', 5),
            'ratings.require_comment_below' => SystemSetting::get('ratings.require_comment_below', 0),
            'ratings.window_days' => SystemSetting::get('ratings.window_days', 7),
            
            // Trust indicators
            'trust.show_trip_count' => SystemSetting::get('trust.show_trip_count', true),
            'trust.show_member_since' => SystemSetting::get('trust.show_member_since', true),
            'trust.show_verified_badge' => SystemSetting::get('trust.show_verified_badge', true),
            'trust.minimum_ratings_for_display' => SystemSetting::get('trust.minimum_ratings_for_display', 3),
            
            // Reports
            'reports.enabled' => SystemSetting::get('reports.enabled', true),
            'reports.require_comment' => SystemSetting::get('reports.require_comment', false),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Ratings Settings')
                ->description('Configure rating system behavior')
                ->schema([
                    Toggle::make('ratings.enabled')
                        ->label('Enable Ratings')
                        ->helperText('Allow riders and drivers to rate each other after completed trips')
                        ->default(true),

                    Select::make('ratings.min_value')
                        ->label('Minimum Rating Value')
                        ->options([
                            1 => '1 star',
                        ])
                        ->default(1)
                        ->helperText('Minimum rating value allowed (typically 1)')
                        ->required()
                        ->visible(fn ($get) => $get('ratings.enabled')),

                    Select::make('ratings.max_value')
                        ->label('Maximum Rating Value')
                        ->options([
                            3 => '3 stars',
                            4 => '4 stars',
                            5 => '5 stars',
                        ])
                        ->default(5)
                        ->helperText('Maximum rating value allowed (typically 5)')
                        ->required()
                        ->visible(fn ($get) => $get('ratings.enabled')),

                    Select::make('ratings.require_comment_below')
                        ->label('Require Comment Below')
                        ->options([
                            0 => 'No requirement',
                            1 => '1 star',
                            2 => '2 stars',
                            3 => '3 stars',
                        ])
                        ->default(0)
                        ->helperText('Require a comment for ratings at or below this value. Helps identify issues.')
                        ->visible(fn ($get) => $get('ratings.enabled')),

                    Select::make('ratings.window_days')
                        ->label('Rating Window')
                        ->options([
                            3 => '3 days',
                            7 => '7 days',
                            14 => '14 days',
                            30 => '30 days',
                        ])
                        ->default(7)
                        ->helperText('Number of days after trip completion that users can submit ratings')
                        ->required()
                        ->visible(fn ($get) => $get('ratings.enabled')),
                ]),

            Section::make('Trust Indicators')
                ->description('Configure what trust information is displayed to users')
                ->schema([
                    Toggle::make('trust.show_trip_count')
                        ->label('Show Trip Count')
                        ->helperText('Display total completed trips on user profiles')
                        ->default(true),

                    Toggle::make('trust.show_member_since')
                        ->label('Show Member Since')
                        ->helperText('Display account creation date on profiles')
                        ->default(true),

                    Toggle::make('trust.show_verified_badge')
                        ->label('Show Verified Badge')
                        ->helperText('Display verification badge for approved drivers')
                        ->default(true),

                    TextInput::make('trust.minimum_ratings_for_display')
                        ->label('Minimum Ratings for Display')
                        ->numeric()
                        ->default(3)
                        ->helperText('Minimum number of ratings required before showing average rating publicly')
                        ->required(),
                ]),

            Section::make('Reports Settings')
                ->description('Configure user reporting functionality')
                ->schema([
                    Toggle::make('reports.enabled')
                        ->label('Enable Reports')
                        ->helperText('Allow users to report other users, rides, or messages for trust & safety')
                        ->default(true),

                    Toggle::make('reports.require_comment')
                        ->label('Require Comment')
                        ->helperText('Require users to provide a comment when submitting a report')
                        ->default(false)
                        ->visible(fn ($get) => $get('reports.enabled')),
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

