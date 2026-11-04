<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewActivityLog);
    }

    public function view(User $user, ActivityLog $model): bool
    {
        return $user->hasPermission(Permission::ViewActivityLog);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $model): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
