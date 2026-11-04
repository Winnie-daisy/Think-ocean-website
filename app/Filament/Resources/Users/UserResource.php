<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('email')->email()->required()->maxLength(190)->unique('users', 'email', ignoreRecord: true),
            Radio::make('role')
                ->options(Role::class)
                ->required()
                ->default(Role::Contributor)
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->helperText(fn (?User $record) => $record?->is(auth()->user()) ? 'You cannot change your own role.' : null)
                ->rule(fn (?User $record) => static::keepsAnAdministrator($record, 'role'))
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Account active')
                ->helperText('Inactive users cannot sign in.')
                ->default(true)
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->rule(fn (?User $record) => static::keepsAnAdministrator($record, 'is_active')),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->rule(Password::defaults())
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : 'At least 12 characters including letters and numbers.'),
        ]);
    }

    /** Prevent removing the last active administrator. */
    private static function keepsAnAdministrator(?User $record, string $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $field): void {
            if (! $record?->isAdministrator()) {
                return;
            }

            $losesAdmin = $field === 'role'
                ? ($value instanceof Role ? $value : Role::tryFrom((string) $value)) !== Role::Administrator
                : ! $value;

            $otherAdmins = User::query()
                ->where('role', Role::Administrator)
                ->where('is_active', true)
                ->whereKeyNot($record->getKey())
                ->exists();

            if ($losesAdmin && ! $otherAdmins) {
                $fail('There must always be at least one active administrator.');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->color('gray')->sortable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Active' : 'Inactive')
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->placeholder('Never')->sortable(),
            ])
            ->filters([SelectFilter::make('role')->options(Role::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
