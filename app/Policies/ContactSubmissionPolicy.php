<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function view(User $user, ContactSubmission $model): bool
    {
        return $user->hasPermission(Permission::ViewSubmissions);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactSubmission $model): bool
    {
        return $user->hasPermission(Permission::ManageSubmissions);
    }

    public function delete(User $user, ContactSubmission $model): bool
    {
        return $user->hasPermission(Permission::ManageSubmissions);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
