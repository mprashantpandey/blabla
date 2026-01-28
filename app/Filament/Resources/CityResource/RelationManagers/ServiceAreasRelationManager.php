<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceAreasRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceAreas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Forms\Components\Textarea::make('polygon')
                    ->label('Polygon Points (JSON)')
                    ->helperText('Array of {lat, lng} objects')
                    ->required(fn (Forms\Get $get) => $get('type') === 'polygon')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'polygon')
                    ->rows(5),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

