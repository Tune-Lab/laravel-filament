<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Actions\App\RefundedAction;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $recordTitleAttribute = 'subscription_id';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->state($this->cachedMountedTableActionRecord->load('user')->toArray())
            ->schema([
                Section::make()->schema([
                    TextEntry::make('user.full_name')
                        ->formatStateUsing(function (string $state) {
                            if ($this->cachedMountedTableActionRecord->user->hasRole(UserRole::SUPER_ADMIN)) {
                                return $state;
                            }

                            $route = route('filament.admin.resources.users.view', $this->cachedMountedTableActionRecord->user_id);

                            return new HtmlString("<a href='$route' class='underline' target='_blank'>$state</a>");
                        })
                        ->color('gray'),
                    TextEntry::make('subscription_id')->color('gray'),
                    TextEntry::make('price')->money('USD')->color('gray'),
                    TextEntry::make('credit_card')->label('Credit Card (last 4 digits)')->color('gray'),
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn (string $state): string => str($state)->upper())
                        ->color(fn (string $state): string => match ($state) {
                            TransactionStatus::SUCCESSFUL => 'success',
                            TransactionStatus::REFUNDED => 'info',
                        }),
                    TextEntry::make('refund_reason')->color('gray'),
                    TextEntry::make('hosted_invoice_url')
                        ->label('Stripe Invoice URL')
                        ->formatStateUsing(fn ($state) => new HtmlString("<a href='$state' class='underline' target='_blank'>$state</a>"))
                        ->color('gray')
                        ->extraAttributes(['style' => 'word-break: break-all;']),
                ]),
            ]);
    }

    /**
     * @throws \Exception
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subscription_id')
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
            ->headerActions([
                //
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
            ]);
    }
}
