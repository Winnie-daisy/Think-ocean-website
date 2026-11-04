<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function view(User $user, Subscriber $model): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Subscriber $model): bool
    {
        return false;
    }

    /** Removing an address is how an unsubscribe request is honoured. */
    public function delete(User $user, Subscriber $model): bool
    {
        return $user->hasPermission(Permission::ManageSubmissions);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
