<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'App Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()->schema([
                    Forms\Components\TextInput::make('group')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('name')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('details')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(50)
                        ->unique(
                            Setting::class,
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Forms\Get $get) => $rule->where('key', $get('key'))->where('group', $get('group')),
                        ),
                    Forms\Components\TextInput::make('value')
                        ->visible(fn (Forms\Get $get) => $get('group') === 'certificate' && $get('key') === 'percentage')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('value')
                        ->hidden(fn (Forms\Get $get) => $get('group') === 'certificate' && $get('key') === 'percentage')
                        ->required()
                        ->maxLength(65535)->columnSpanFull(),
                    Forms\Components\Toggle::make('is_visible')
                        ->default(true)
                        ->onIcon('heroicon-m-check')
                        ->offIcon('heroicon-m-x-mark')
                        ->offColor('danger')
                        ->reactive()
                        ->helperText(fn (string $state) => !$state ? 'You will not be able to view or edit an invisible setting once it is created' : ''),
                ]),
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
                Tables\Columns\TextColumn::make('group')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('key')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('value')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options(fn () => Setting::whereIsVisible(true)->pluck('group', 'group')->toArray())
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('is_visible', true)->count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_visible', true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
