<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_if(auth()->id() === $this->form->getModelInstance()->id, 403);
    }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = User::find($data['id']);

        if (!$user->hasExactRoles($data['role'])) {
            $user->removeRole($user->role);
            $user->assignRole($data['role']);
        }

        return parent::mutateFormDataBeforeSave(Arr::except($data, 'role'));
    }
}
