<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use function Filament\Support\format_money;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $users = User::all();

        return [
            Stat::make('Total clients', $users->filter(fn (User $user) => $user->hasExactRoles(UserRole::CLIENT))->count())
                ->icon('heroicon-s-user-group')
                ->description('Users with any roles except Super Admin')
                ->descriptionIcon('heroicon-s-academic-cap')
                ->color('success'),
            Stat::make('Total subscribers', $users->filter(fn (User $user) => $user->subscribed())->count())
                ->icon('heroicon-s-rectangle-stack')
                ->description('Based on stripe data')
                ->descriptionIcon('heroicon-s-clipboard-document-check')
                ->color('info'),
            Stat::make('Total income', format_money(Transaction::whereStatus(TransactionStatus::SUCCESSFUL)->sum('price'), 'USD'))
                ->icon('heroicon-s-building-library')
                ->description('Based on successful transactions')
                ->descriptionIcon('heroicon-s-banknotes')
                ->color('success'),
        ];
    }
}
