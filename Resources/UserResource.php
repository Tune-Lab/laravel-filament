<?php

namespace App\Filament\Resources;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\TransactionsRelationManager;
use App\Models\User;
use BezhanSalleh\FilamentShield\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phpsa\FilamentPasswordReveal\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Details')->schema([
                    Hidden::make('id'),
                    TextInput::make('first_name')->required()->minLength(2)->maxLength(25),
                    TextInput::make('last_name')->required()->minLength(2)->maxLength(25),
                    TextInput::make('email')->email()->required()->maxLength(50)->unique(User::class, ignoreRecord: true)->rule('email:filter,rfc,dns'),
                    Select::make('status')->required()->options(UserStatus::asSelectArray())->default(UserStatus::ACTIVE),
                    Password::make('password')
                        ->password()
                        ->minLength(8)
                        ->revealable()
                        ->generatable()
                        ->copyable(!app()->isLocal())
                        ->required(fn (Page $livewire) => $livewire instanceof CreateRecord)
                        ->hidden(fn (Page $livewire): bool => $livewire instanceof ViewRecord)
                        ->same('passwordConfirmation')
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                    Password::make('passwordConfirmation')
                        ->password()
                        ->revealable()
                        ->copyable(!app()->isLocal())
                        ->label('Password Confirmation')
                        ->required(fn (Page $livewire): bool => $livewire instanceof CreateRecord)
                        ->hidden(fn (Page $livewire): bool => $livewire instanceof ViewRecord)
                        ->minLength(8)
                        ->dehydrated(false),
                    FileUpload::make('profile_photo_path')
                        ->label('Avatar')
                        ->disk(config('jetstream.profile_photo_disk'))
                        ->directory('profile-photos')
                        ->visibility('public')
                        ->image()
                        ->previewable()
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            null,
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->columnSpanFull(),
                    Radio::make('role')
                        ->required()
                        ->options(Role::where('name', '<>', UserRole::SUPER_ADMIN)->get()->mapWithKeys(fn (Role $role) => [$role->name => str($role->name)->headline()->value()])->toArray())
                        ->default('client')
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
                Tables\Columns\ImageColumn::make('profile_photo_url')->label('Avatar')->circular()->size(48)->toggleable(),
                Tables\Columns\TextColumn::make('first_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('last_name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->tooltip(!app()->isLocal() ? 'Copy to clipboard' : null)
                    ->copyable(!app()->isLocal())
                    ->copyMessage('Email address copied')
                    ->copyMessageDuration(1500)
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('roles.name')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subscription_status')->label('Subscription')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        SubscriptionStatus::ACTIVE => 'success',
                        SubscriptionStatus::CANCELED => 'warning',
                        SubscriptionStatus::ENDED => 'danger',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->upper())
                    ->color(fn (string $state): string => match ($state) {
                        UserStatus::ACTIVE => 'success',
                        UserStatus::BANNED => 'danger',
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
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('Verified email')
                    ->query(fn (Builder $query): Builder => $query->where('email_verified_at', '!=', null)),
                Tables\Filters\Filter::make('Unverified email')
                    ->query(fn (Builder $query): Builder => $query->where('email_verified_at', null)),
                Tables\Filters\SelectFilter::make('roles')->relationship('roles', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(UserStatus::asSelectArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->hidden(fn (User $user) => auth()->id() === $user->id),
                Tables\Actions\Action::make('ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-no-symbol')
                    ->modalHeading(fn (User $user) => "Blocking $user->email")
                    ->modalDescription('Are you sure you want to BAN this user?')
                    ->hidden(fn (User $user) => auth()->id() === $user->id || $user->hasRole(UserRole::SUPER_ADMIN) || $user->status->value === UserStatus::BANNED)
                    ->disabled(fn (User $user) => $user->status->value === UserStatus::BANNED)
                    ->action(fn (User $user) => $user->update(['status' => UserStatus::BANNED])),
                Tables\Actions\Action::make('unban')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-lock-open')
                    ->modalHeading(fn (User $user) => "Unblocking $user->email")
                    ->modalDescription('Are you sure you want to UNBAN this user?')
                    ->hidden(fn (User $user) => auth()->id() === $user->id || $user->hasRole(UserRole::SUPER_ADMIN) || $user->status->value !== UserStatus::BANNED)
                    ->disabled(fn (User $user) => $user->status->value !== UserStatus::BANNED)
                    ->action(fn (User $user) => $user->update(['status' => UserStatus::ACTIVE])),
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
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->role(Role::all()->filter(fn (Role $role) => $role->name !== UserRole::SUPER_ADMIN));
    }

    public static function getResourceEntitiesSchema(): ?array
    {
        return collect((new FilamentShield)->getResources())->sortKeys()->reduce(function ($entities, $entity) {
            $entities[] = Section::make()
                ->extraAttributes(['class' => 'border-0 shadow-lg dark:bg-gray-900'])
                ->schema([
                    Toggle::make($entity['resource'])
                        ->label(FilamentShield::getLocalizedResourceLabel($entity['fqcn']))
                        ->onIcon('heroicon-s-lock-open')
                        ->offIcon('heroicon-s-lock-closed')
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) use ($entity) {
                            collect(Utils::getGeneralResourcePermissionPrefixes())->each(function ($permission) use ($set, $entity, $state) {
                                $set($permission . '_' . $entity['resource'], $state);
                            });

                            if (!$state) {
                                $set('select_all', false);
                            }

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->dehydrated(false),
                    Fieldset::make('Permissions')
                        ->label(__('filament-shield::filament-shield.column.permissions'))
                        ->extraAttributes(['class' => 'text-primary-600 border-gray-300 dark:border-gray-800'])
                        ->columns([
                            'default' => 2,
                            'md' => 3,
                            'lg' => 3,
                            'xl' => 4,
                        ])
                        ->schema(static::getResourceEntityPermissionsSchema($entity)),
                ])
                ->columns()
                ->columnSpan(1);

            return $entities;
        }, collect())
            ->toArray();
    }

    public static function getResourceEntityPermissionsSchema($entity): ?array
    {
        return collect(Utils::getGeneralResourcePermissionPrefixes())->reduce(function ($permissions/** @phpstan ignore-line */, $permission) use ($entity) {
            $permissions[] = Checkbox::make($permission . '_' . $entity['resource'])
                ->label(FilamentShield::getLocalizedResourcePermissionLabel($permission))
                ->extraAttributes(['class' => 'text-primary-600'])
                ->afterStateHydrated(function (Set $set, Get $get, $record) use ($entity, $permission) {
                    if (is_null($record)) {
                        return;
                    }

                    $set($permission . '_' . $entity['resource'], $record->checkPermissionTo($permission . '_' . $entity['resource']));

                    static::refreshResourceEntityStateAfterHydrated($record, $set, $entity['resource']);

                    static::refreshSelectAllStateViaEntities($set, $get);
                })
                ->reactive()
                ->afterStateUpdated(function (Set $set, Get $get, $state) use ($entity) {
                    static::refreshResourceEntityStateAfterUpdate($set, $get, Str::of($entity['resource']));

                    if (!$state) {
                        $set($entity['resource'], false);
                        $set('select_all', false);
                    }

                    static::refreshSelectAllStateViaEntities($set, $get);
                })
                ->dehydrated(fn ($state): bool => $state);

            return $permissions;
        }, collect())
            ->toArray();
    }

    protected static function refreshSelectAllStateViaEntities(Set $set, Get $get): void
    {
        $entityStates = collect((new FilamentShield)->getResources())
            ->when(Utils::isPageEntityEnabled(), fn ($entities) => $entities->merge(FilamentShield::getPages()))
            ->when(Utils::isWidgetEntityEnabled(), fn ($entities) => $entities->merge(FilamentShield::getWidgets()))
            ->when(Utils::isCustomPermissionEntityEnabled(), fn ($entities) => $entities->merge(static::getCustomEntities()))
            ->map(function ($entity) use ($get) {
                if (is_array($entity)) {
                    return (bool) $get($entity['resource']);
                }

                return (bool) $get($entity);
            });

        if ($entityStates->containsStrict(false) === false) {
            $set('select_all', true);
        }

        if ($entityStates->containsStrict(false) === true) {
            $set('select_all', false);
        }
    }

    protected static function refreshResourceEntityStateAfterUpdate(Set $set, Get $get, string $entity): void
    {
        $permissionStates = collect(Utils::getGeneralResourcePermissionPrefixes())
            ->map(function ($permission) use ($get, $entity) {
                return (bool) $get($permission . '_' . $entity);
            });

        if ($permissionStates->containsStrict(false) === false) {
            $set($entity, true);
        }

        if ($permissionStates->containsStrict(false) === true) {
            $set($entity, false);
        }
    }

    protected static function refreshResourceEntityStateAfterHydrated(Model $record, Set $set, string $entity): void
    {
        $permissions = $record->getPermissionsViaRoles() ?: $record->permissions;

        $entities = $permissions->pluck('name')
            ->reduce(function ($roles, $role) {
                $roles[$role] = Str::afterLast($role, '_');

                return $roles;
            }, collect())
            ->values()
            ->groupBy(function ($item) {
                return $item;
            })->map->count()
            ->reduce(function ($counts, $role, $key) {
                if ($role > 1 && $role == count(Utils::getGeneralResourcePermissionPrefixes())) {
                    $counts[$key] = true;
                } else {
                    $counts[$key] = false;
                }

                return $counts;
            }, []);

        // set entity's state if one are all permissions are true
        if (Arr::exists($entities, $entity) && Arr::get($entities, $entity)) {
            $set($entity, true);
        } else {
            $set($entity, false);
            $set('select_all', false);
        }
    }

    protected static function getPageEntityPermissionsSchema(): ?array
    {
        return collect(FilamentShield::getPages())->sortKeys()->reduce(function ($pages, $page) {
            $pages[] = Grid::make()
                ->schema([
                    Checkbox::make($page)
                        ->label(FilamentShield::getLocalizedPageLabel($page))
                        ->inline()
                        ->afterStateHydrated(function (Set $set, Get $get, $record) use ($page) {
                            if (is_null($record)) {
                                return;
                            }

                            $set($page, $record->checkPermissionTo($page));

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            if (!$state) {
                                $set('select_all', false);
                            }

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->dehydrated(fn ($state): bool => $state),
                ])
                ->columns(1)
                ->columnSpan(1);

            return $pages;
        }, []);
    }

    protected static function getWidgetEntityPermissionSchema(): ?array
    {
        return collect(FilamentShield::getWidgets())->reduce(function ($widgets, $widget) {
            $widgets[] = Grid::make()
                ->schema([
                    Checkbox::make($widget)
                        ->label(FilamentShield::getLocalizedWidgetLabel($widget))
                        ->inline()
                        ->afterStateHydrated(function (Set $set, Get $get, $record) use ($widget) {
                            if (is_null($record)) {
                                return;
                            }

                            $set($widget, $record->checkPermissionTo($widget));

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            if (!$state) {
                                $set('select_all', false);
                            }

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->dehydrated(fn ($state): bool => $state),
                ])
                ->columns(1)
                ->columnSpan(1);

            return $widgets;
        }, []);
    }

    protected static function getCustomEntities(): ?Collection
    {
        $resourcePermissions = collect();

        collect((new FilamentShield)->getResources())->each(function ($entity) use ($resourcePermissions) {
            collect(Utils::getGeneralResourcePermissionPrefixes())->map(function ($permission) use ($resourcePermissions, $entity) {
                $resourcePermissions->push((string) Str::of($permission . '_' . $entity['resource']));
            });
        });

        $entityPermissions = $resourcePermissions
            ->merge(FilamentShield::getPages())
            ->merge(FilamentShield::getWidgets())
            ->values();

        return Permission::whereNotIn('name', $entityPermissions)->pluck('name');
    }

    protected static function getCustomEntitiesPermissionsSchema(): ?array
    {
        return collect(static::getCustomEntities())->reduce(function ($customEntities, $customPermission) {
            $customEntities[] = Grid::make()
                ->schema([
                    Checkbox::make($customPermission)
                        ->label(Str::of($customPermission)->headline())
                        ->inline()
                        ->afterStateHydrated(function (Set $set, Get $get, $record) use ($customPermission) {
                            if (is_null($record)) {
                                return;
                            }

                            $set($customPermission, $record->checkPermissionTo($customPermission));

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                            if (!$state) {
                                $set('select_all', false);
                            }

                            static::refreshSelectAllStateViaEntities($set, $get);
                        })
                        ->dehydrated(fn ($state): bool => $state),
                ])
                ->columns(1)
                ->columnSpan(1);

            return $customEntities;
        }, []);
    }
}
