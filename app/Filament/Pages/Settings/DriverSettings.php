<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;

class DriverSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static string $view = 'filament.pages.settings.driver-settings';
    protected static ?string $navigationLabel = 'Drivers';
    protected static ?string $title = 'Driver Onboarding & Verification';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 6;

    public function mount(): void
    {
        $requiredDocs = SystemSetting::get('driver.required_documents', '[]');
        $requiredDocs = is_string($requiredDocs) ? json_decode($requiredDocs, true) : $requiredDocs;
        if (!is_array($requiredDocs)) {
            $requiredDocs = [];
        }

        $this->form->fill([
            'enabled' => SystemSetting::get('driver.enabled', true),
            'require_verification' => SystemSetting::get('driver.require_verification', true),
            'min_age_years' => SystemSetting::get('driver.min_age_years', 18),
            'require_selfie' => SystemSetting::get('driver.require_selfie', true),
            'required_documents' => $requiredDocs,
            'max_doc_file_mb' => SystemSetting::get('driver.max_doc_file_mb', 8),
            'allowed_doc_mimes' => SystemSetting::get('driver.allowed_doc_mimes', 'jpg,jpeg,png,pdf'),
            'auto_approve' => SystemSetting::get('driver.auto_approve', false),
            'blocked_if_docs_expired' => SystemSetting::get('driver.blocked_if_docs_expired', false),
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('General Settings')
                ->description('Control whether users can apply to become drivers and how they are verified')
                ->schema([
                    Toggle::make('enabled')
                        ->label('Enable Driver Onboarding')
                        ->helperText('Allow users to apply to become drivers. When disabled, the driver application feature is hidden from the app.')
                        ->default(true),
                    Toggle::make('require_verification')
                        ->label('Require Admin Verification')
                        ->helperText('If enabled, all driver applications must be reviewed and approved by an admin. If disabled, drivers are automatically approved.')
                        ->default(true)
                        ->reactive(),
                    Toggle::make('auto_approve')
                        ->label('Auto Approve Drivers (Demo Mode)')
                        ->helperText('⚠️ WARNING: For demo/testing purposes only. When enabled, drivers are automatically approved if all requirements are met, bypassing admin review.')
                        ->default(false)
                        ->visible(fn ($get) => $get('require_verification')),
                ])->columns(2),

            Section::make('Age & Identity Requirements')
                ->description('Set minimum age requirements and identity verification needs')
                ->schema([
                    TextInput::make('min_age_years')
                        ->label('Minimum Driver Age')
                        ->numeric()
                        ->default(18)
                        ->suffix('years')
                        ->minValue(18)
                        ->maxValue(100)
                        ->helperText('Minimum age required to become a driver (typically 18 or 21 years)')
                        ->required(),
                    Toggle::make('require_selfie')
                        ->label('Require Selfie Photo')
                        ->helperText('Require drivers to upload a selfie photo during the application process for identity verification')
                        ->default(true),
                ])->columns(2),

            Section::make('Required Documents')
                ->description('Define which documents drivers must upload. Each document has a unique key (for system use) and a label (shown to users).')
                ->schema([
                    Repeater::make('required_documents')
                        ->label('Document Types')
                        ->schema([
                            TextInput::make('key')
                                ->label('Document Key')
                                ->required()
                                ->maxLength(50)
                                ->alphaNum()
                                ->placeholder('license')
                                ->helperText('Unique identifier used by the system (e.g., license, id_card, vehicle_rc). Use lowercase letters and underscores only.'),
                            TextInput::make('label')
                                ->label('Display Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Driving License')
                                ->helperText('The name shown to users when they upload this document (e.g., "Driving License", "Government ID")'),
                            Toggle::make('required')
                                ->label('Required')
                                ->helperText('If enabled, drivers cannot submit their application without uploading this document')
                                ->default(true),
                        ])
                        ->columns(3)
                        ->defaultItems(3)
                        ->addActionLabel('Add Document Type')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'New Document')
                        ->helperText('Add, remove, or reorder document types. The "key" is for system use, while "label" is what users see.'),
                ]),

            Section::make('File Upload Settings')
                ->description('Configure file size limits and allowed file types for document uploads')
                ->schema([
                    TextInput::make('max_doc_file_mb')
                        ->label('Maximum File Size')
                        ->numeric()
                        ->default(8)
                        ->suffix('MB')
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Maximum file size allowed for document uploads (1-50 MB). Larger files may take longer to upload.')
                        ->required(),
                    TextInput::make('allowed_doc_mimes')
                        ->label('Allowed File Types')
                        ->default('jpg,jpeg,png,pdf')
                        ->helperText('Comma-separated list of allowed file types. Common options: jpg,jpeg,png,pdf. Do not include spaces.')
                        ->required(),
                    Toggle::make('blocked_if_docs_expired')
                        ->label('Block Drivers with Expired Documents')
                        ->helperText('If enabled, drivers cannot create rides if any of their required documents have passed their expiry date')
                        ->default(false),
                ])->columns(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Convert required documents array to JSON
        if (isset($data['required_documents'])) {
            $data['required_documents'] = json_encode($data['required_documents']);
        }

        foreach ($data as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string');
            SystemSetting::set("driver.{$key}", $value, $type, 'driver');
        }

        SystemSetting::clearCache();

        Notification::make()
            ->title('Driver settings saved successfully')
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
