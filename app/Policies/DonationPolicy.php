<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Donation;
use App\Models\User;

/** Donations are created by donors and updated by Stripe only; staff can view them. */
class DonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function view(User $user, Donation $model): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Donation $model): bool
    {
        return false;
    }

    public function delete(User $user, Donation $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
