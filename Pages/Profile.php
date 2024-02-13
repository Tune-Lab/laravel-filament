<?php

namespace App\Filament\Pages;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Phpsa\FilamentPasswordReveal\Password;

class Profile extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static string $view = 'filament.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'first_name' => auth()->user()->first_name,
            'last_name' => auth()->user()->last_name,
            'email' => auth()->user()->email,
            'avatar' => auth()->user()->profile_photo_path,
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $state = $this->form->getState();

            if ($state['new_password']) {
                $state['password'] = Hash::make($state['new_password']);
            }

            $user = auth()->user();

            if ($state['avatar'] instanceof TemporaryUploadedFile) {
                $user->updateProfilePhoto($state['avatar']);
            }

            if (!$state['avatar'] && $user->profile_photo_path) {
                $user->deleteProfilePhoto();
            }

            $user->update($state);

            if ($state['new_password']) {
                $this->updateSessionPassword($user);
            }

            $this->reset(['data.current_password', 'data.new_password', 'data.new_password_confirmation']);

            Notification::make()->title('Your profile has been updated.')->success()->send();
        } catch (Halt $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());

            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }
    }

    protected function updateSessionPassword(User $user): void
    {
        request()->session()->put([
            'password_hash_' . auth()->getDefaultDriver() => $user->getAuthPassword(),
        ]);
    }

    public function getCancelButtonUrlProperty(): string
    {
        return static::getUrl();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('General')->columns()->collapsible()->schema([
                TextInput::make('first_name')->required()->maxLength(50),
                TextInput::make('last_name')->required()->maxLength(50),
                TextInput::make('email')->email()->required()->unique(User::class, ignorable: auth()->user())->rule('email:filter,rfc,dns'),
                FileUpload::make('avatar')
                    ->disk(config('jetstream.profile_photo_disk'))
                    ->directory('profile-photos')
                    ->visibility('public')
                    ->storeFiles(false)
                    ->maxSize(10240)
                    ->openable()
                    ->previewable()
                    ->downloadable()
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])->columnSpanFull(),
            ]),
            Section::make('Update Password')->columns()->collapsible()->schema([
                Password::make('current_password')
                    ->password()
                    ->requiredWith('new_password')
                    ->currentPassword()
                    ->autocomplete('off')
                    ->columnSpan(1)
                    ->revealable()
                    ->copyable(!app()->isLocal()),
                Grid::make()->schema([
                    Password::make('new_password')
                        ->password()
                        ->confirmed()
                        ->minLength(6)
                        ->maxLength(25)
                        ->autocomplete('new-password')
                        ->revealable()
                        ->generatable()
                        ->copyable(!app()->isLocal()),
                    Password::make('new_password_confirmation')
                        ->password()
                        ->requiredWith('new_password')
                        ->label('Confirm Password')
                        ->autocomplete('new-password')
                        ->revealable()
                        ->copyable(!app()->isLocal()),
                ]),
            ]),
        ])->statePath('data');
    }
}
