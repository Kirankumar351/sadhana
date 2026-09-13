<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AgentDefinitionResource\Pages;
use App\Models\AgentDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentDefinitionResource extends Resource
{
    protected static ?string $model = AgentDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'AI';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required(),
                Forms\Components\Textarea::make('name')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('family')
                    ->required(),
                Forms\Components\Textarea::make('goal')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('tools')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('model_tier')
                    ->required(),
                Forms\Components\TextInput::make('max_steps')
                    ->required()
                    ->numeric()
                    ->default(12),
                Forms\Components\TextInput::make('max_cost_paise_per_run')
                    ->required()
                    ->numeric()
                    ->default(5000),
                Forms\Components\TextInput::make('timeout_sec')
                    ->required()
                    ->numeric()
                    ->default(300),
                Forms\Components\TextInput::make('autonomy')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('schedule_cron'),
                Forms\Components\Textarea::make('trigger_events')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('family')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_tier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('max_steps')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_cost_paise_per_run')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('timeout_sec')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('autonomy')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('schedule_cron')
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
            'index' => Pages\ListAgentDefinitions::route('/'),
            'create' => Pages\CreateAgentDefinition::route('/create'),
            'edit' => Pages\EditAgentDefinition::route('/{record}/edit'),
        ];
    }
}
