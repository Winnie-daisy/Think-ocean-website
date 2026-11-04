<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\NavigationItem;
use App\Models\User;

class NavigationItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageNavigation);
    }

    public function view(User $user, NavigationItem $model): bool
    {
        return $user->hasPermission(Permission::ManageNavigation);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageNavigation);
    }

    public function update(User $user, NavigationItem $model): bool
    {
        return $user->hasPermission(Permission::ManageNavigation);
    }

    public function delete(User $user, NavigationItem $model): bool
    {
        return $user->hasPermission(Permission::ManageNavigation);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
