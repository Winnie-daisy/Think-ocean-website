<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function user(Role $role = Role::Contributor, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    protected function admin(): User
    {
        return $this->user(Role::Administrator);
    }

    protected function editor(): User
    {
        return $this->user(Role::Editor);
    }

    protected function contributor(): User
    {
        return $this->user(Role::Contributor);
    }

    protected function reviewer(): User
    {
        return $this->user(Role::Reviewer);
    }
}
