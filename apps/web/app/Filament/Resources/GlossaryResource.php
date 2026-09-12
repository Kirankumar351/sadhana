<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GlossaryResource\Pages;
use App\Models\Glossary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GlossaryResource extends Resource
{
    protected static ?string $model = Glossary::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Localisation';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('term_en')
                    ->required(),
                Forms\Components\TextInput::make('term_te'),
                Forms\Components\TextInput::make('term_hi'),
                Forms\Components\TextInput::make('term_ta'),
                Forms\Components\TextInput::make('rule')
                    ->required(),
                Forms\Components\TextInput::make('category'),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('term_en')
                    ->searchable(),
                Tables\Columns\TextColumn::make('term_te')
                    ->searchable(),
                Tables\Columns\TextColumn::make('term_hi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('term_ta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rule')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->searchable(),
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
            'index' => Pages\ListGlossaries::route('/'),
            'create' => Pages\CreateGlossary::route('/create'),
            'edit' => Pages\EditGlossary::route('/{record}/edit'),
        ];
    }
}
