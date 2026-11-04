<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ContentRevision;
use App\Models\User;

class ContentRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function view(User $user, ContentRevision $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContentRevision $model): bool
    {
        return false;
    }

    public function delete(User $user, ContentRevision $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
