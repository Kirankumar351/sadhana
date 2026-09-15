<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ScrapeSourceResource\Pages;
use App\Models\ScrapeSource;
use App\Services\Ingestion\ScrapeRunner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where notifications come from.
 *
 * The screen answers three questions at a glance: is this source running, did its last run
 * find anything, and is anything waiting for review because of it. A timestamp alone
 * answered none of them — a run that found nothing and a run that is broken looked the same.
 *
 * "Pull now" runs the source in the request and reports what it did. Nothing it finds is
 * published: every new notification lands in the Review Queue as a draft.
 */
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
                    ->required()
                    ->maxLength(120),

                Forms\Components\Select::make('parser_class')
                    ->label('Parser')
                    ->options(ScrapeSource::PARSERS)
                    ->required()
                    ->helperText('How this site is read. Each board publishes differently, so each major board has its own parser.'),

                Forms\Components\TextInput::make('url')
                    ->label('Listing URL')
                    ->url()
                    ->required()
                    ->maxLength(700)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('frequency_min')
                    ->label('Check every')
                    ->suffix('minutes')
                    ->required()
                    ->numeric()
                    ->minValue(15)
                    ->default(60)
                    ->helperText('No faster than 15 minutes. These are slow government servers and we are their guest.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Active sources are pulled automatically while the scheduler and queue worker are running.'),

                Forms\Components\Placeholder::make('last_summary')
                    ->label('Last run')
                    ->content(fn (?ScrapeSource $record): string => $record?->last_run_at
                        ? $record->last_run_at->diffForHumans().' — '.($record->last_summary ?? 'no summary recorded')
                        : 'Never run.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (ScrapeSource $record): string => $record->parserLabel()),

                Tables\Columns\TextColumn::make('url')
                    ->label('Listing')
                    ->limit(40)
                    ->tooltip(fn (ScrapeSource $record): string => $record->url)
                    ->url(fn (ScrapeSource $record): string => $record->url, shouldOpenInNewTab: true),

                Tables\Columns\TextColumn::make('last_summary')
                    ->label('Last run')
                    ->placeholder('Never run')
                    ->description(fn (ScrapeSource $record): ?string => $record->last_run_at?->diffForHumans())
                    ->wrap(),

                Tables\Columns\TextColumn::make('notifications_count')
                    ->label('In review')
                    ->counts(['notifications' => fn (Builder $query) => $query->where('status', 'pending_review')])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label('Failures')
                    ->badge()
                    // Three in a row is a layout change, and that is when ops gets an email.
                    ->color(fn (int $state): string => match (true) {
                        $state >= 3 => 'danger',
                        $state > 0 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency_min')
                    ->label('Every')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('frequency_min')
            ->actions([
                Tables\Actions\Action::make('pull')
                    ->label('Pull now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ScrapeSource $record): string => "Pull {$record->name} now?")
                    ->modalDescription('Fetches the official site now, politely paced, so it can take a minute. New notifications become drafts in the Review Queue. Nothing is published.')
                    ->action(fn (ScrapeSource $record) => static::pull(collect([$record]))),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pullSelected')
                        ->label('Pull selected now')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->modalDescription('Each source is fetched in turn and paced, so several can take a few minutes. Drafts land in the Review Queue.')
                        ->action(fn (Collection $records) => static::pull($records)),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  Collection<int, ScrapeSource>  $records
     */
    protected static function pull(Collection $records): void
    {
        // Politely paced requests against slow servers outlast the default 30-second limit.
        set_time_limit(0);

        $runner = app(ScrapeRunner::class);

        foreach ($records as $record) {
            $result = $runner->run($record);

            Notification::make()
                ->title($record->name)
                ->body(ucfirst($result->summary()))
                ->when($result->failed, fn (Notification $n) => $n->danger(), fn (Notification $n) => $result->created > 0 ? $n->success() : $n->info())
                ->persistent()
                ->send();
        }
    }

    public static function getRelations(): array
    {
        return [];
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
