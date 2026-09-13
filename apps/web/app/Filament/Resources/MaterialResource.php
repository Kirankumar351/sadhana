<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'name'),
                Forms\Components\TextInput::make('uploaded_by')
                    ->numeric(),
                Forms\Components\TextInput::make('slug')
                    ->required(),
                Forms\Components\Textarea::make('title')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('subject'),
                Forms\Components\TextInput::make('topic'),
                Forms\Components\TextInput::make('locale')
                    ->required(),
                Forms\Components\TextInput::make('file_path'),
                Forms\Components\TextInput::make('file_size_kb')
                    ->numeric(),
                Forms\Components\TextInput::make('file_type')
                    ->required(),
                Forms\Components\Textarea::make('html_content')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('source_type')
                    ->required(),
                Forms\Components\TextInput::make('edited_by')
                    ->numeric(),
                Forms\Components\TextInput::make('source_url'),
                Forms\Components\Toggle::make('copyright_confirmed')
                    ->required(),
                Forms\Components\TextInput::make('copyright_ip'),
                Forms\Components\DateTimePicker::make('copyright_confirmed_at'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('reviewed_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('reviewed_at'),
                Forms\Components\TextInput::make('rejection_reason'),
                Forms\Components\TextInput::make('download_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('exam.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('uploaded_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable(),
                Tables\Columns\TextColumn::make('topic')
                    ->searchable(),
                Tables\Columns\TextColumn::make('locale')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_size_kb')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('edited_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_url')
                    ->searchable(),
                Tables\Columns\IconColumn::make('copyright_confirmed')
                    ->boolean(),
                Tables\Columns\TextColumn::make('copyright_ip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('copyright_confirmed_at')
                    ->dateTime()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('download_count')
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
                Tables\Columns\TextColumn::make('deleted_at')
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
