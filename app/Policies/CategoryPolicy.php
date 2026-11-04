<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function view(User $user, Category $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function update(User $user, Category $model): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function delete(User $user, Category $model): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
