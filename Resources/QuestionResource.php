<?php

namespace App\Filament\Resources;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Category;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component as Livewire;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Question Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('General')->schema([
                        Forms\Components\Section::make()->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\Select::make('pack_id')
                                ->relationship('pack', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->default(request()->has('pack') ? request()->integer('pack') : null)
                                ->afterStateUpdated(fn (Livewire $livewire, Forms\Set $set, ?string $state) => $set('number', intval($state) === $livewire->record?->pack_id ? $livewire->record->number : Question::where('pack_id', intval($state))->max('number') + 1))
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('name')
                                ->required()
                                ->placeholder('Enter question name')
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('number')
                                ->numeric()
                                ->lazy()
                                ->minValue(1)
                                ->disabled(fn (Forms\Get $get) => !$get('pack_id'))
                                ->default(fn (Livewire $livewire, Forms\Get $get, ?string $state) => $get('pack_id') ? Question::where('pack_id', $get('pack_id'))->max('number') + 1 : null)
                                ->afterStateUpdated(function (Forms\Get $get, ?string $state) {
                                    if (Question::firstWhere([['pack_id', '=', $get('pack_id')], ['number', '=', intval($state)]])) {
                                        Notification::make()
                                            ->title("Question # $state has already exists in this pack")
                                            ->body('Try to change number or select other pack.')
                                            ->danger()
                                            ->send();
                                    }
                                }),
                            Forms\Components\Toggle::make('is_free')
                                ->onIcon('heroicon-m-check')
                                ->offIcon('heroicon-m-currency-dollar')
                                ->offColor('danger'),
                        ])->columns(),
                    ])->icon('heroicon-m-question-mark-circle'),
                    Forms\Components\Wizard\Step::make('Description')->schema([
                        TinyEditor::make('description')
                            ->fileAttachmentsDisk(config('filesystems.default'))
                            ->fileAttachmentsVisibility(config('filesystems.disks.public.visibility'))
                            ->fileAttachmentsDirectory(config('filament-tinyeditor.profiles.full.upload_directory'))
                            ->profile(config('filament-tinyeditor.profile'))
                            ->direction(config('filament-tinyeditor.direction'))
                            ->toolbarSticky(config('filament-tinyeditor.toolbar_sticky'))
                            ->maxLength(config('filament-tinyeditor.max_length'))
                            ->setRelativeUrls(false)
                            ->showMenuBar()
                            ->columnSpanFull()
                            ->required(),
                    ])->icon('heroicon-m-document-text'),
                    Forms\Components\Wizard\Step::make('Answers')->schema([
                        Forms\Components\Repeater::make('questionAnswers')
                            ->label('Answers')
                            ->addActionLabel('Add new answer')
                            ->helperText(fn (Forms\Get $get) => !collect($get('questionAnswers'))->filter(fn (array $answer) => $answer['is_correct'])->count() ? new HtmlString('<p style="color: #f87171">At least one answer must be correct!</p>') : '')
                            ->relationship()
                            ->required()
                            ->minItems(2)
                            ->maxItems(10)
                            ->collapsible()
                            ->cloneable()
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                TinyEditor::make('description')
                                    ->fileAttachmentsDisk(config('filesystems.default'))
                                    ->fileAttachmentsVisibility(config('filesystems.disks.public.visibility'))
                                    ->fileAttachmentsDirectory(config('filament-tinyeditor.profiles.full.upload_directory'))
                                    ->profile(config('filament-tinyeditor.profile'))
                                    ->direction(config('filament-tinyeditor.direction'))
                                    ->toolbarSticky(config('filament-tinyeditor.toolbar_sticky'))
                                    ->maxLength(config('filament-tinyeditor.max_length'))
                                    ->setRelativeUrls(false)
                                    ->showMenuBar()
                                    ->columnSpanFull()
                                    ->required(),
                                Forms\Components\Toggle::make('is_correct')
                                    ->onIcon('heroicon-m-check')
                                    ->offIcon('heroicon-m-x-mark')
                                    ->offColor('danger')
                                    ->fixIndistinctState()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('explanation', null)),
                                TinyEditor::make('explanation')
                                    ->visible(fn (Forms\Get $get): ?bool => $get('is_correct'))
                                    ->required(fn (Forms\Get $get): ?bool => $get('is_correct'))
                                    ->fileAttachmentsDisk(config('filesystems.default'))
                                    ->fileAttachmentsVisibility(config('filesystems.disks.public.visibility'))
                                    ->fileAttachmentsDirectory(config('filament-tinyeditor.profiles.full.upload_directory'))
                                    ->profile(config('filament-tinyeditor.profile'))
                                    ->direction(config('filament-tinyeditor.direction'))
                                    ->toolbarSticky(config('filament-tinyeditor.toolbar_sticky'))
                                    ->maxLength(config('filament-tinyeditor.max_length'))
                                    ->setRelativeUrls(false)
                                    ->showMenuBar()
                                    ->columnSpanFull(),
                            ])->columnSpanFull(),
                    ])->icon('heroicon-m-pencil-square'),
                ])->columnSpanFull()->skippable(),
            ]),
        ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->tooltip(!app()->isLocal() ? 'Copy to clipboard' : null)
                    ->copyable(!app()->isLocal())
                    ->copyMessage('UUID copied')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')->limit(50)->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pack.category.name')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('pack.name')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('number')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_free')->boolean()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('question_answers_count')
                    ->counts('questionAnswers')
                    ->label('Answers')
                    ->badge()
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
                Tables\Filters\SelectFilter::make('pack')
                    ->relationship('pack', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => Category::all()->pluck('name', 'id')->toArray())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        !empty($data['values']),
                        fn () => $query->whereHas('pack', fn (Builder $q) => $q->whereIn('category_id', $data['values']))))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_free'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
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
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'view' => Pages\ViewQuestion::route('/{record}'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
