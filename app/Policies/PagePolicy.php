<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends ContentPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return ! $model->is_system && parent::delete($user, $model);
    }
}
