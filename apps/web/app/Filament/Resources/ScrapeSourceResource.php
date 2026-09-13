<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ScrapeSourceResource\Pages;
use App\Models\ScrapeSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScrapeSourceResource extends Resource
{
    protected static ?string $model = ScrapeSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->required(),
                Forms\Components\TextInput::make('parser_class')
                    ->required(),
                Forms\Components\TextInput::make('frequency_min')
                    ->required()
                    ->numeric()
                    ->default(30),
                Forms\Components\DateTimePicker::make('last_run_at'),
                Forms\Components\DateTimePicker::make('last_success_at'),
                Forms\Components\TextInput::make('consecutive_failures')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parser_class')
                    ->searchable(),
                Tables\Columns\TextColumn::make('frequency_min')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_success_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScrapeSources::route('/'),
            'create' => Pages\CreateScrapeSource::route('/create'),
            'edit' => Pages\EditScrapeSource::route('/{record}/edit'),
        ];
    }
}
