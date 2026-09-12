<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use App\Support\Locale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The question bank.
 *
 * ANSWER KEYS ARE THE WHOLE RISK HERE. A fluent question with a wrong key teaches
 * thousands of people something false, and they carry it into the exam hall. Checking each
 * key against a source is the slowest step in the content pipeline and it stays that way.
 *
 * Two mechanisms enforce that:
 *   - `approved_at` is null until a person approves the question. `scopeServable` excludes
 *     unapproved rows, so nothing unreviewed can reach a quiz even by accident.
 *   - `is_disputed` pulls a question from rotation the moment users argue about it. The
 *     argument is settled offline; the question stops being served immediately.
 */
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Quiz';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $unapproved = static::getModel()::query()->whereNull('approved_at')->count();

        return $unapproved > 0 ? (string) $unapproved : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Question')->schema([
                Forms\Components\Tabs::make('Translations')->tabs(
                    collect(Locale::active())->map(fn (string $code) => Forms\Components\Tabs\Tab::make(
                        (string) config("locales.supported.{$code}.native", $code)
                    )->schema([
                        Forms\Components\Textarea::make("question.{$code}")
                            ->label('Question')
                            ->rows(3)
                            ->required($code === 'en'),

                        /**
                         * Options are a repeater per locale, not a free-text field.
                         *
                         * The Telugu option order MUST match the English order, because
                         * `correct_index` is a single number shared across both. A
                         * mismatch means the Telugu reader is shown a different correct
                         * answer from the English reader, which is the worst kind of bug
                         * here: silent, and only discovered by the student in the exam.
                         */
                        Forms\Components\Repeater::make("options.{$code}")
                            ->label('Options — keep the same order in every language')
                            ->simple(Forms\Components\TextInput::make('option')->required())
                            ->minItems(2)
                            ->maxItems(6)
                            ->defaultItems(4)
                            ->reorderable(false),

                        Forms\Components\Textarea::make("explanation.{$code}")
                            ->label('Explanation — this is the actual learning')
                            ->rows(3),
                    ]))->all()
                )->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Answer key')
                ->description('Checked against a source by a person. This is the slowest step in the pipeline and it stays that way.')
                ->schema([
                    Forms\Components\TextInput::make('correct_index')
                        ->label('Correct option (0 = first)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->required()
                        ->helperText('Zero-indexed. 0 is option A.'),

                    Forms\Components\Toggle::make('is_disputed')
                        ->label('Disputed — pull from rotation')
                        ->helperText('Use this the moment users argue about the key. Settle the argument offline; stop serving it now.'),
                ])->columns(2),

            Forms\Components\Section::make('Classification')->schema([
                Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'short_name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('subject')
                    ->datalist(['Indian Polity', 'Indian Economy', 'Geography', 'History', 'Current Affairs', 'Telangana Movement', 'Reasoning', 'General Studies'])
                    ->helperText('Drives the weak-area analysis and the study planner.'),

                Forms\Components\TextInput::make('topic'),

                Forms\Components\Select::make('difficulty')
                    ->options(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'])
                    ->default('medium'),

                Forms\Components\Toggle::make('is_current_affairs')
                    ->label('Current affairs')
                    ->helperText('Five of the ten daily questions come from this pool. It is the binding constraint on the daily quiz.'),

                Forms\Components\TextInput::make('source_year')
                    ->numeric()
                    ->label('Previous-year paper')
                    ->minValue(2000)
                    ->maxValue((int) date('Y')),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')
                    ->formatStateUsing(fn (Question $r): string => (string) $r->getTranslation('question', 'en', true))
                    ->wrap()
                    ->limit(70)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('subject', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('subject')->badge()->toggleable(),

                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->color(fn (string $s): string => match ($s) {
                        'easy' => 'success', 'hard' => 'danger', default => 'warning',
                    }),

                Tables\Columns\IconColumn::make('is_current_affairs')->boolean()->label('CA'),

                /**
                 * Telugu completeness, shown as a column.
                 *
                 * An English-only question inside a Telugu quiz is precisely the silent
                 * failure that loses users: they do not report it, they just stop doing
                 * the quiz. Making the gap visible in the list is how it gets fixed.
                 */
                Tables\Columns\IconColumn::make('has_telugu')
                    ->label('తె')
                    ->boolean()
                    ->getStateUsing(fn (Question $r): bool => filled($r->getTranslation('question', 'te', false)))
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('approved_at')
                    ->label('Key verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('warning'),

                Tables\Columns\IconColumn::make('is_disputed')
                    ->boolean()
                    ->label('Disputed')
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('times_served')->numeric()->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('accuracy')
                    ->label('% correct')
                    ->getStateUsing(fn (Question $r): string => $r->accuracy() !== null ? $r->accuracy().'%' : '—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unapproved')
                    ->label('Key not yet verified')
                    ->query(fn (Builder $query) => $query->whereNull('approved_at')),

                Tables\Filters\Filter::make('no_telugu')
                    ->label('Missing Telugu')
                    ->query(fn (Builder $query) => $query->whereNull('question->te')),

                Tables\Filters\Filter::make('disputed')
                    ->query(fn (Builder $query) => $query->where('is_disputed', true)),

                Tables\Filters\SelectFilter::make('subject')
                    ->options(fn (): array => Question::query()
                        ->whereNotNull('subject')
                        ->distinct()
                        ->pluck('subject', 'subject')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Question $r): bool => $r->approved_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Approve this answer key?')
                    ->modalDescription('A wrong key teaches thousands of people something false and they carry it into the exam hall.')
                    ->form([
                        Forms\Components\Checkbox::make('checked')
                            ->label('I checked this answer key against a source')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                        Forms\Components\Checkbox::make('order')
                            ->label('The Telugu options are in the same order as the English options')
                            ->accepted()->validationMessages(['accepted' => 'Required.']),
                    ])
                    ->action(fn (Question $r) => $r->update([
                        'approved_at' => now(),
                        'approved_by' => auth()->id(),
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
