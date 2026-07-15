<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OperationalModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->canOperate();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->canOperate();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->canDeleteRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canDeleteRecords();
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->isSuperAdmin();
    }
}
