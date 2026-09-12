<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Models\Exam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->required(),
                Forms\Components\Textarea::make('name')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('short_name')
                    ->required(),
                Forms\Components\TextInput::make('conducting_body')
                    ->required(),
                Forms\Components\TextInput::make('official_website'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('eligibility_summary')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('exam_pattern')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('syllabus')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('meta_title')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('meta_description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('faq')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('state'),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\Toggle::make('has_interview')
                    ->required(),
                Forms\Components\Toggle::make('has_mains')
                    ->required(),
                Forms\Components\TextInput::make('view_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('conducting_body')
                    ->searchable(),
                Tables\Columns\TextColumn::make('official_website')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_interview')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_mains')
                    ->boolean(),
                Tables\Columns\TextColumn::make('view_count')
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
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}
