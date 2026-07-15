<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $model): bool
    {
        return $user->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function update(User $user, User $model): bool
    {
        return $user->canManageUsers();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->canManageUsers() && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
