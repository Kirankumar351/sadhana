<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ExamNotificationResource\Pages;
use App\Models\ExamNotification;
use App\Services\Ingestion\NotificationPublisher;
use App\Support\Locale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The notification editor — the highest-stakes screen in the product.
 *
 * This resource writes the fields that a student will act on to apply for a job: the
 * eligibility criteria, the dates and the fees. Per docs/02-DATA-OWNERSHIP.md this table is
 * the owner of record for all three, and nothing may publish here without a person having
 * checked the values against the official PDF.
 *
 * Two design decisions worth stating:
 *
 *   1. THE AGE REFERENCE DATE IS A SEPARATE, REQUIRED-IN-PRACTICE FIELD. Indian
 *      notifications compute age "as on" a stated cut-off, commonly 1 July, and NOT as on
 *      the application deadline. Conflating them moves a boundary candidate across the
 *      line in either direction, which is the exact failure the eligibility engine exists
 *      to prevent.
 *
 *   2. PUBLISH IS A SEPARATE ACTION WITH A CHECKLIST, not a status dropdown someone can
 *      flick past. Saving a draft is cheap; publishing reaches lakhs of people.
 */
class ExamNotificationResource extends Resource
{
    protected static ?string $model = ExamNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?int $navigationSort = 2;

    /**
     * The badge is the review queue depth. Vol 2 sets a 2-hour review SLA, and a count
     * sitting in the sidebar is what makes an overdue queue visible without anyone
     * remembering to go and look.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()->where('status', 'pending_review')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::query()->where('status', 'pending_review')->count() > 0
            ? 'warning'
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Tabs::make()->columnSpanFull()->tabs([

                // ---------------------------------------------------------------- basics
                Forms\Components\Tabs\Tab::make('Basics')->schema([

                    // One tab per active locale. Adding a language is a config flip, so
                    // this is built from config rather than hardcoded.
                    Forms\Components\Tabs::make('Translations')->tabs(
                        collect(Locale::active())->map(fn (string $code) => Forms\Components\Tabs\Tab::make(
                            (string) config("locales.supported.{$code}.native", $code)
                        )->schema([
                            Forms\Components\TextInput::make("title.{$code}")
                                ->label('Title')
                                ->required($code === 'en')
                                ->maxLength(300),
                            Forms\Components\Textarea::make("description.{$code}")
                                ->label('Description')
                                ->rows(4),
                        ]))->all()
                    )->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Never translated. One slug across every language, or backlinks fragment.')
                        ->maxLength(200),

                    Forms\Components\TextInput::make('organisation')->required()->maxLength(160),

                    Forms\Components\Select::make('exam_id')
                        ->relationship('exam', 'short_name')
                        ->searchable()
                        ->preload()
                        ->label('Exam'),

                    Forms\Components\Select::make('job_type')
                        ->options([
                            'government' => 'Government',
                            'private' => 'Private',
                            'psu' => 'PSU',
                            'contract' => 'Contract',
                        ])
                        ->default('government')
                        ->required(),

                    Forms\Components\TextInput::make('total_vacancies')->numeric()->minValue(0),
                ])->columns(2),

                // ------------------------------------------------------------ eligibility
                Forms\Components\Tabs\Tab::make('Eligibility')
                    ->badge('drives the matching engine')
                    ->schema([
                        Forms\Components\Placeholder::make('warning')
                            ->label('')
                            ->content('These fields decide what every user is told about their own career. Copy them from the official PDF — never from a news report or another portal.')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('min_qualification')
                            ->options([
                                '10th' => '10th / SSC',
                                '12th' => 'Intermediate / 12th',
                                'iti' => 'ITI',
                                'diploma' => 'Diploma',
                                'degree' => 'Degree (any stream)',
                                'btech' => 'B.Tech / B.E.',
                                'pg' => 'Post Graduation',
                                'mbbs' => 'MBBS',
                                'phd' => 'PhD',
                            ])
                            ->label('Minimum qualification'),

                        Forms\Components\TextInput::make('min_age')->numeric()->minValue(14)->maxValue(70),
                        Forms\Components\TextInput::make('max_age')->numeric()->minValue(14)->maxValue(70),

                        Forms\Components\DatePicker::make('age_reference_date')
                            ->label('Age calculated as on')
                            ->helperText('The "as on" date in the PDF — usually 1 July. NOT the application deadline. Getting this wrong moves borderline candidates across the line.')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('age_relaxation')
                            ->label('Age relaxation (years)')
                            ->keyLabel('Category')
                            ->valueLabel('Years')
                            ->default(['obc' => 3, 'sc' => 5, 'st' => 5, 'pwd' => 10])
                            ->columnSpanFull(),

                        Forms\Components\Select::make('allowed_states')
                            ->multiple()
                            ->options(['TS' => 'Telangana', 'AP' => 'Andhra Pradesh'])
                            ->label('Restricted to states')
                            ->helperText('Leave empty for all-India. Empty means no restriction, not "no states".'),

                        Forms\Components\Select::make('gender_restriction')
                            ->options(['any' => 'Any', 'male' => 'Male only', 'female' => 'Female only'])
                            ->default('any'),
                    ])->columns(2),

                // ------------------------------------------------------------------ dates
                Forms\Components\Tabs\Tab::make('Dates and money')->schema([
                    Forms\Components\DatePicker::make('notification_date'),
                    Forms\Components\DatePicker::make('apply_start_date'),
                    Forms\Components\DatePicker::make('apply_end_date')->label('Last date to apply'),
                    Forms\Components\DatePicker::make('fee_payment_end_date'),
                    Forms\Components\DatePicker::make('exam_date'),
                    Forms\Components\DatePicker::make('admit_card_date'),

                    Forms\Components\KeyValue::make('application_fee')
                        ->label('Application fee by category (rupees)')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('salary_min')->numeric()->prefix('Rs'),
                    Forms\Components\TextInput::make('salary_max')->numeric()->prefix('Rs'),
                ])->columns(2),

                // ------------------------------------------------------------- provenance
                Forms\Components\Tabs\Tab::make('Source')->schema([
                    Forms\Components\TextInput::make('official_pdf_url')
                        ->url()
                        ->label('Official notification PDF')
                        ->helperText('Every notification must link to its official source. Non-negotiable for trust.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('registration_url')
                        ->url()
                        ->label('Registration link')
                        ->helperText('One-time registration, where the board requires it before applying (TGPSC OTR, APPSC OTPR). Leave blank if the apply link handles registration.')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('apply_url')->url()->label('Apply link')->columnSpanFull(),
                    Forms\Components\TextInput::make('source_url')->url()->columnSpanFull(),

                    Forms\Components\Placeholder::make('verified')
                        ->label('Verification')
                        ->content(fn (?ExamNotification $record): string => $record?->verified_at
                            ? 'Verified '.$record->verified_at->format('d M Y H:i').' by '.($record->verifier?->name ?? 'unknown')
                            : 'Not yet verified.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->formatStateUsing(fn (ExamNotification $record): string => (string) $record->getTranslation('title', 'en', true))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('slug', 'like', "%{$search}%")
                        ->orWhere('organisation', 'like', "%{$search}%"))
                    ->wrap()
                    ->limit(60),

                Tables\Columns\TextColumn::make('organisation')->toggleable()->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending_review' => 'warning',
                        'cancelled', 'expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_vacancies')->numeric()->sortable()->label('Posts'),

                Tables\Columns\TextColumn::make('apply_end_date')
                    ->date('d M Y')
                    ->sortable()
                    ->label('Last date')
                    // Red only inside three days, matching the student portal, so the two
                    // sides of the product mean the same thing by the same colour.
                    ->color(fn (?ExamNotification $record): ?string => $record?->isUrgent() ? 'danger' : null),

                Tables\Columns\IconColumn::make('verified_at')
                    ->boolean()
                    ->label('Verified')
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('published_at')->dateTime('d M H:i')->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'pending_review' => 'Pending review',
                    'published' => 'Published',
                    'expired' => 'Expired',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\Filter::make('unverified')
                    ->label('Not yet verified')
                    ->query(fn (Builder $query) => $query->whereNull('verified_at')),
                Tables\Filters\Filter::make('closing_soon')
                    ->label('Closing within 7 days')
                    ->query(fn (Builder $query) => $query->closingWithin(7)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                /**
                 * Publishing is deliberately friction-ful.
                 *
                 * The checklist is not decoration: a content lead who ticks four boxes has
                 * read four specific things, and the confirmation is recorded against
                 * their name. A single "Publish" button invites a reflex click, and the
                 * cost of a reflex click here is somebody's year.
                 */
                Tables\Actions\Action::make('publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ExamNotification $record): bool => $record->status !== 'published')
                    ->requiresConfirmation()
                    ->modalHeading('Publish this notification?')
                    ->modalDescription('It will reach every user following this exam within minutes. A wrong date costs someone a year.')
                    ->form([
                        Forms\Components\Checkbox::make('c1')
                            ->label('I checked the last date against the official PDF')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                        Forms\Components\Checkbox::make('c2')
                            ->label('I checked the age limits and the "as on" reference date')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                        Forms\Components\Checkbox::make('c3')
                            ->label('I checked the qualification and any state restriction')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                        Forms\Components\Checkbox::make('c4')
                            ->label('The official PDF link opens and is the right notification')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                    ])
                    // Through the publisher, so this screen and the review queue record
                    // the same verification signature.
                    ->action(fn (ExamNotification $record) => app(NotificationPublisher::class)
                        ->publish($record, auth()->user())),

                Tables\Actions\Action::make('unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->visible(fn (ExamNotification $record): bool => $record->status === 'published')
                    ->requiresConfirmation()
                    ->modalDescription('Use this the moment an error is reported. Correcting fast costs less trust than being right first time.')
                    ->action(fn (ExamNotification $record) => app(NotificationPublisher::class)->unpublish($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExamNotifications::route('/'),
            'create' => Pages\CreateExamNotification::route('/create'),
            'edit' => Pages\EditExamNotification::route('/{record}/edit'),
        ];
    }
}
