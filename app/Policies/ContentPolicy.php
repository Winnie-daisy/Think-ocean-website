<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ReviewStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for publishable content (pages, projects, news posts).
 *
 * Contributors may only edit content they authored, and not while it is
 * awaiting review. Only editors and administrators may publish.
 */
abstract class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateContent);
    }

    public function update(User $user, Model $model): bool
    {
        if ($model->trashed()) {
            return false;
        }

        if ($user->hasPermission(Permission::EditAnyContent)) {
            return true;
        }

        return $user->hasPermission(Permission::CreateContent)
            && $this->isAuthor($user, $model)
            && $model->review_status !== ReviewStatus::InReview;
    }

    public function submit(User $user, Model $model): bool
    {
        return $this->update($user, $model) && $model->review_status->canBeSubmitted();
    }

    public function publish(User $user, Model $model): bool
    {
        return ! $model->trashed() && $user->hasPermission(Permission::PublishContent);
    }

    /** Return for changes or reject a submission. */
    public function review(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::PublishContent) && $model->review_status === ReviewStatus::InReview;
    }

    public function unpublish(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::PublishContent) && $model->is_published;
    }

    public function delete(User $user, Model $model): bool
    {
        if ($user->hasPermission(Permission::DeleteContent)) {
            return true;
        }

        // Contributors may remove their own drafts that have never been public.
        return $user->hasPermission(Permission::CreateContent)
            && $this->isAuthor($user, $model)
            && ! $model->is_published
            && $model->revisions()->where('action', 'published')->doesntExist();
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function viewRevisions(User $user, Model $model): bool
    {
        return $user->hasPermission(Permission::ViewContent);
    }

    protected function isAuthor(User $user, Model $model): bool
    {
        return $model->author_id !== null && (int) $model->author_id === (int) $user->getKey();
    }
}
