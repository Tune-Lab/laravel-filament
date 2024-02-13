<?php

namespace App\Filament\Resources\PackResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Questions';

    /**
     * @throws \Exception
     */
    public function table(Table $table): Table
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
                Tables\Columns\TextColumn::make('name')->limit(75)->searchable()->sortable(),
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
                Tables\Filters\TernaryFilter::make('is_free'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->url(fn () => route('filament.admin.resources.questions.create', ['pack' => $this->ownerRecord->id]), true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Tables\Actions\ViewAction $action) => route('filament.admin.resources.questions.view', $action->getRecord()->id)),
                Tables\Actions\EditAction::make()
                    ->url(fn (Tables\Actions\EditAction $action) => route('filament.admin.resources.questions.edit', $action->getRecord()->id)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->url(fn () => route('filament.admin.resources.questions.create', ['pack' => $this->ownerRecord->id]), true),
            ]);
    }
}
