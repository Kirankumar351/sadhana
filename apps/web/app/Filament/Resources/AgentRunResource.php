<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AgentRunResource\Pages;
use App\Models\AgentRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentRunResource extends Resource
{
    protected static ?string $model = AgentRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'AI';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                Forms\Components\TextInput::make('agent_definition_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\TextInput::make('trigger')
                    ->required(),
                Forms\Components\TextInput::make('trigger_ref'),
                Forms\Components\Textarea::make('input')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('halt_reason'),
                Forms\Components\Textarea::make('output')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('steps_used')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('cost_paise')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('duration_ms')
                    ->numeric(),
                Forms\Components\TextInput::make('drafts_created')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('error')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('started_at'),
                Forms\Components\DateTimePicker::make('finished_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('agent_definition_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trigger')
                    ->searchable(),
                Tables\Columns\TextColumn::make('trigger_ref')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('halt_reason')
                    ->searchable(),
                Tables\Columns\TextColumn::make('steps_used')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost_paise')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('drafts_created')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('finished_at')
                    ->dateTime()
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
            'index' => Pages\ListAgentRuns::route('/'),
            'create' => Pages\CreateAgentRun::route('/create'),
            'edit' => Pages\EditAgentRun::route('/{record}/edit'),
        ];
    }
}
