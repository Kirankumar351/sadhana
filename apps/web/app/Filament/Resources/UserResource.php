<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required(),
                Forms\Components\DateTimePicker::make('phone_verified_at'),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password(),
                Forms\Components\TextInput::make('preferred_locale')
                    ->required(),
                Forms\Components\TextInput::make('reputation')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_verified_selected')
                    ->required(),
                Forms\Components\TextInput::make('verified_selected_exam'),
                Forms\Components\Toggle::make('is_staff')
                    ->required(),
                Forms\Components\Toggle::make('is_banned')
                    ->required(),
                Forms\Components\TextInput::make('ban_reason'),
                Forms\Components\DateTimePicker::make('last_active_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('preferred_locale')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reputation')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified_selected')
                    ->boolean(),
                Tables\Columns\TextColumn::make('verified_selected_exam')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_staff')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_banned')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ban_reason')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_active_at')
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
