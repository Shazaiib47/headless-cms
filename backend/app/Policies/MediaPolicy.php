<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Editor);
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasRole(Role::Admin, Role::Editor);
    }

    /** Deletion is administrator-only. */
    public function delete(User $user, Media $media): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
