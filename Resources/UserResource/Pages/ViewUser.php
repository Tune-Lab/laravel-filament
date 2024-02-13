<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-o-pencil-square')->hidden(fn (User $user) => auth()->id() === $user->id),
            Action::make('ban')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(fn (User $user) => "Blocking $user->email")
                ->modalIcon('heroicon-o-no-symbol')
                ->modalDescription('Are you sure you want to BAN this user?')
                ->hidden(fn (User $user) => auth()->id() === $user->id || $user->hasRole(UserRole::SUPER_ADMIN) || $user->status->value === UserStatus::BANNED)
                ->action(fn (User $user) => $user->update(['status' => UserStatus::BANNED])),
            Action::make('unban')
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-lock-open')
                ->modalHeading(fn (User $user) => "Unblocking $user->email")
                ->modalDescription('Are you sure you want to UNBAN this user?')
                ->hidden(fn (User $user) => auth()->id() === $user->id || $user->hasRole(UserRole::SUPER_ADMIN) || $user->status->value !== UserStatus::BANNED)
                ->action(fn (User $user) => $user->update(['status' => UserStatus::ACTIVE])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('first_name')->color('gray'),
                TextEntry::make('last_name')->color('gray'),
                TextEntry::make('email')->copyable(!app()->isLocal())->copyMessage('Email address copied')->copyMessageDuration(1500)->color('gray'),
                TextEntry::make('email_verified_at')->color('gray'),
                ImageEntry::make('profile_photo_path')->label('Avatar')->circular()->columnSpanFull(),
                TextEntry::make('role')->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        UserRole::SUPER_ADMIN => 'danger',
                        UserRole::MANAGER => 'primary',
                        UserRole::CLIENT => 'gray',
                        default => 'info'
                    }),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        UserStatus::ACTIVE => 'success',
                        UserStatus::BANNED => 'danger',
                    }),
                TextEntry::make('subscription_status')->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionStatus::ACTIVE => 'success',
                        SubscriptionStatus::CANCELED => 'warning',
                        SubscriptionStatus::ENDED => 'danger',
                    }),
                TextEntry::make('subscription_ends_at')
                    ->visible(fn () => $this->record->subscribed() && $this->record->subscription()?->canceled())
                    ->color('gray'),
                TextEntry::make('subscription_bills_at')
                    ->visible(fn () => $this->record->subscribed() && !$this->record->subscription()?->canceled())
                    ->color('gray'),
                TextEntry::make('created_at')->color('gray'),
                TextEntry::make('updated_at')->color('gray'),
            ])->columns(),
        ]);
    }
}
