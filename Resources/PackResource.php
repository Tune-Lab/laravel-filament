<?php

namespace App\Filament\Resources;

use App\Enums\PackStatus;
use App\Enums\UserRole;
use App\Filament\Resources\PackResource\Pages;
use App\Filament\Resources\PackResource\RelationManagers\QuestionsRelationManager;
use App\Models\Pack;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Question Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\Select::make('user_id')
                        ->relationship('user', 'email')
                        ->required()
                        ->searchable()
                        ->default(auth()->id())
                        ->disabled(!auth()->user()->hasRole(UserRole::SUPER_ADMIN)),
                    Forms\Components\Select::make('category_id')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('difficulty_id')
                        ->relationship('difficulty', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('uuid')
                        ->label('UUID')
                        ->hidden(fn (Page $livewire) => $livewire instanceof CreateRecord)
                        ->disabled(),
                    Forms\Components\TextInput::make('price')
                        ->hidden()
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('$'),
                    Forms\Components\TextInput::make('name')
                        ->unique('packs', 'name', ignoreRecord: true)
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->rows(5)
                        ->maxLength(2500)
                        ->columnSpanFull(),
                    Forms\Components\Radio::make('status')
                        ->options(PackStatus::asSelectArray())
                        ->default(PackStatus::DRAFT)
                        ->required()
                        ->inline(),
                ])->columns(),
            ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->sortable()
                    ->searchable()
                    ->url(fn (Pack $pack) => $pack->user->hasRole(UserRole::SUPER_ADMIN) ? null : route('filament.admin.resources.users.view', $pack->user_id), true),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('difficulty.name')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) <= $column->getCharacterLimit() ? null : $state;
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->hidden()
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        PackStatus::PUBLISHED => 'success',
                        PackStatus::DRAFT => 'danger',
                        default => 'info'
                    })->sortable(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->counts('questions')
                    ->label('Questions')
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
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'email')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('difficulty')
                    ->relationship('difficulty', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')->options(PackStatus::asSelectArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('status')
                    ->label(fn (Pack $pack) => $pack->status->value === PackStatus::DRAFT ? 'Publish' : 'Return to Draft')
                    ->icon(fn (Pack $pack) => $pack->status->value === PackStatus::DRAFT ? 'heroicon-m-cloud-arrow-up' : 'heroicon-m-cloud-arrow-down')
                    ->color(fn (Pack $pack) => $pack->status->value === PackStatus::DRAFT ? 'success' : 'info')
                    ->action(function (Pack $pack): void {
                        $status = $pack->status->value === PackStatus::DRAFT ? PackStatus::PUBLISHED : PackStatus::DRAFT;

                        $pack->update(['status' => $status]);

                        Notification::make()->title('Pack status changed to ' . str($status)->upper()->value())->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPacks::route('/'),
            'create' => Pages\CreatePack::route('/create'),
            'edit' => Pages\EditPack::route('/{record}/edit'),
        ];
    }
}
