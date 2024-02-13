<?php

namespace App\Filament\Resources;

use App\Actions\App\RefundedAction;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Purchases';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subscription_id';

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uuid')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subscription_id')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->copyable(!app()->isLocal())
                    ->tooltip(!app()->isLocal() ? 'Copy to clipboard' : null)
                    ->copyMessage('Subscription ID copied')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('user.email')
                    ->sortable()
                    ->searchable()
                    ->url(fn (Transaction $transaction): ?string => $transaction->user->hasRole(UserRole::SUPER_ADMIN) ? null : route('filament.admin.resources.users.view', $transaction->user_id)),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credit_card')
                    ->searchable()
                    ->tooltip(!app()->isLocal() ? 'Copy to clipboard' : null)
                    ->copyable(!app()->isLocal())
                    ->copyMessage('Card number copied')
                    ->copyMessageDuration(1500),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        TransactionStatus::SUCCESSFUL => 'success',
                        TransactionStatus::REFUNDED => 'info',
                    })->sortable(),
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
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')->options(TransactionStatus::asSelectArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('refund')
                    ->hidden(fn (Transaction $transaction): bool => $transaction->status->value === TransactionStatus::REFUNDED || Transaction::whereUserId($transaction->user_id)->get()->last()->id !== $transaction->id)
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-arrow-uturn-left')
                    ->modalHeading(fn (Transaction $transaction) => "Refund transaction: $transaction->subscription_id")
                    ->modalDescription('Are you sure you want to REFUND this transaction?')
                    ->form([
                        Forms\Components\Textarea::make('refund_reason')->required()->maxLength(255),
                    ])
                    ->action(function (array $data, Transaction $transaction): void {
                        if (RefundedAction::handle($transaction, $data['refund_reason'])) {
                            Notification::make()->title('Transaction has been refunded successfully!')->success()->send();
                        } else {
                            Notification::make()->title('An error occurred during the refund process!')->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateActions([
                //
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
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
