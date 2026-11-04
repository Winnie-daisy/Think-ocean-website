<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function view(User $user, Tag $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function update(User $user, Tag $model): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function delete(User $user, Tag $model): bool
    {
        return $user->hasPermission(Permission::ManageTaxonomy);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
