<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdCampaignResource\Pages;
use App\Models\AdCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdCampaignResource extends Resource
{
    protected static ?string $model = AdCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Money';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('advertiser_id')
                    ->relationship('advertiser', 'name'),
                Forms\Components\TextInput::make('advertiser_name')
                    ->required(),
                Forms\Components\TextInput::make('contact_phone')
                    ->tel(),
                Forms\Components\TextInput::make('creative_path'),
                Forms\Components\Textarea::make('headline')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('body')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('target_url')
                    ->required(),
                Forms\Components\TextInput::make('placement')
                    ->required(),
                Forms\Components\Textarea::make('target_districts')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('target_exams')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('target_locales')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('target_qualifications')
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('starts_at')
                    ->required(),
                Forms\Components\DatePicker::make('ends_at')
                    ->required(),
                Forms\Components\TextInput::make('daily_cap')
                    ->numeric(),
                Forms\Components\TextInput::make('total_impression_cap')
                    ->numeric(),
                Forms\Components\TextInput::make('amount_paise')
                    ->numeric(),
                Forms\Components\TextInput::make('pricing_model')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('approved_by')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('advertiser.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('advertiser_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('creative_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('placement')
                    ->searchable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('daily_cap')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_impression_cap')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paise')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pricing_model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('approved_by')
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
            'index' => Pages\ListAdCampaigns::route('/'),
            'create' => Pages\CreateAdCampaign::route('/create'),
            'edit' => Pages\EditAdCampaign::route('/{record}/edit'),
        ];
    }
}
