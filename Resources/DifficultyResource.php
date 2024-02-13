<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DifficultyResource\Pages;
use App\Models\Difficulty;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DifficultyResource extends Resource
{
    protected static ?string $model = Difficulty::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'App Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->unique('difficulties', 'name', ignoreRecord: true)
                            ->maxLength(50)
                            ->autofocus()
                            ->columnSpanFull()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Hidden::make('slug'),
                        TextInput::make('level')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(10),
                        ColorPicker::make('color')
                            ->required(),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('level')->searchable()->sortable(),
                Tables\Columns\ColorColumn::make('color')->searchable()->sortable()
                    ->tooltip(!app()->isLocal() ? 'Copy to clipboard' : null)
                    ->copyable(!app()->isLocal())
                    ->copyMessage('Color code copied')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('packs_count')
                    ->counts('packs')
                    ->label('Packs')
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('If this difficulty is deleted, all questions and packages related to it will also be deleted.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalDescription('If all difficulties are deleted, all questions and packages related to them will also be deleted.'),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListDifficulties::route('/'),
            'create' => Pages\CreateDifficulty::route('/create'),
            'edit' => Pages\EditDifficulty::route('/{record}/edit'),
        ];
    }
}
