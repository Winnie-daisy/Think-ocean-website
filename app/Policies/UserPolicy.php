<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission(Permission::ManageUsers) && ! $model->is($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
