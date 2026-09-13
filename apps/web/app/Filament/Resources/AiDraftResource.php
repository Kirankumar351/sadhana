<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AiDraftResource\Pages;
use App\Models\AiDraft;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiDraftResource extends Resource
{
    protected static ?string $model = AiDraft::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'AI';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('type')
                    ->required(),
                Forms\Components\Textarea::make('payload')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('source_refs')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('model_confidence')
                    ->numeric(),
                Forms\Components\TextInput::make('flagged_reason'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('reviewed_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('reviewed_at'),
                Forms\Components\TextInput::make('rejection_reason'),
                Forms\Components\TextInput::make('promoted_id')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_confidence')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('flagged_reason')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reviewed_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->searchable(),
                Tables\Columns\TextColumn::make('promoted_id')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListAiDrafts::route('/'),
            'create' => Pages\CreateAiDraft::route('/create'),
            'edit' => Pages\EditAiDraft::route('/{record}/edit'),
        ];
    }
}
