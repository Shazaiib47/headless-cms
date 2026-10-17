<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    /** Any authenticated user (viewer, editor, admin) may read admin content. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Page $page): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Editor);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasRole(Role::Admin, Role::Editor);
    }

    /** Deletion is administrator-only. */
    public function delete(User $user, Page $page): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
