<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageOrganizations(User $user, ?User $model = null): bool
    {
        return (bool) $user->is_admin;
    }

    public function manageUsers(User $user, ?User $model = null): bool
    {
        return (bool) $user->is_admin;
    }
}
