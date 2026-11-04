<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function view(User $user, Media $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UploadMedia);
    }

    public function update(User $user, Media $model): bool
    {
        return $user->hasPermission(Permission::ManageMedia)
            || ($user->hasPermission(Permission::UploadMedia) && (int) $model->uploaded_by === (int) $user->getKey());
    }

    public function delete(User $user, Media $model): bool
    {
        return $user->hasPermission(Permission::ManageMedia);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
