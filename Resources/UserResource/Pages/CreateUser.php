<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create(Arr::except($data, 'role'));

        if ($tenant = Filament::getTenant()) {
            return $this->associateRecordWithTenant($user, $tenant);
        }

        $user->sendEmailVerificationNotification();

        $user->assignRole($data['role']);

        return $user;
    }
}
