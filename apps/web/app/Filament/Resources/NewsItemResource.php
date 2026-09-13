<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsItemResource\Pages;
use App\Models\NewsItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsItemResource extends Resource
{
    protected static ?string $model = NewsItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'AI';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('news_source_id')
                    ->numeric(),
                Forms\Components\TextInput::make('source_name')
                    ->required(),
                Forms\Components\TextInput::make('source_url')
                    ->required(),
                Forms\Components\DateTimePicker::make('published_at'),
                Forms\Components\Textarea::make('raw_text')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('title')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('summary')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('why_it_matters')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('relevance_score')
                    ->numeric(),
                Forms\Components\TextInput::make('dedupe_group'),
                Forms\Components\Textarea::make('exam_tags')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('probability'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('hold_reason'),
                Forms\Components\TextInput::make('approved_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('approved_at'),
                Forms\Components\DatePicker::make('digest_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('news_source_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('relevance_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dedupe_group')
                    ->searchable(),
                Tables\Columns\TextColumn::make('probability')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hold_reason')
                    ->searchable(),
                Tables\Columns\TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('digest_date')
                    ->date()
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
            'index' => Pages\ListNewsItems::route('/'),
            'create' => Pages\CreateNewsItem::route('/create'),
            'edit' => Pages\EditNewsItem::route('/{record}/edit'),
        ];
    }
}
