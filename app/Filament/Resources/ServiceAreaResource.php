<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceAreaResource\Pages;
use App\Models\ServiceArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceAreaResource extends Resource
{
    protected static ?string $model = ServiceArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Service Areas';
    protected static ?string $navigationGroup = 'Locations';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('city_id')
                            ->relationship('city', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options([
                                'circle' => 'Circle',
                                'polygon' => 'Polygon',
                            ])
                            ->required()
                            ->live()
                            ->default('circle'),
                    ])->columns(2),

                Forms\Components\Section::make('Circle Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('center_lat')
                            ->numeric()
                            ->step(0.00000001)
                            ->label('Center Latitude')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'circle'),
                        Forms\Components\TextInput::make('center_lng')
                            ->numeric()
                            ->step(0.00000001)
                            ->label('Center Longitude')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'circle'),
                        Forms\Components\TextInput::make('radius_km')
                            ->numeric()
                            ->step(0.01)
                            ->label('Radius (km)')
                            ->required(fn (Forms\Get $get) => $get('type') === 'circle')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'circle'),
                    ])
                    ->columns(3)
                    ->visible(fn (Forms\Get $get) => $get('type') === 'circle'),

                Forms\Components\Section::make('Polygon Configuration')
                    ->schema([
                        Forms\Components\Textarea::make('polygon')
                            ->label('Polygon Points (JSON)')
                            ->helperText('Array of {lat, lng} objects. Example: [{"lat": 40.7128, "lng": -74.0060}, ...]')
                            ->required(fn (Forms\Get $get) => $get('type') === 'polygon')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'polygon')
                            ->rows(10),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'polygon'),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('city.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'circle' => 'success',
                        'polygon' => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('city_id')
                    ->relationship('city', 'name')
                    ->label('City'),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'circle' => 'Circle',
                        'polygon' => 'Polygon',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('city_id')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceAreas::route('/'),
            'create' => Pages\CreateServiceArea::route('/create'),
            'edit' => Pages\EditServiceArea::route('/{record}/edit'),
        ];
    }
}
