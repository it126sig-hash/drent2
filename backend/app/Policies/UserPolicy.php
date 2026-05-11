<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if (!in_array($user->role, ['superadmin', 'admin_branch'])) {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        return $user->branch_id === $model->branch_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // cannot edit self in MDM
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $model->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // cannot delete self
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $model->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user can reset the password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if ($user->role === 'admin_branch') {
            return $user->branch_id === $model->branch_id;
        }

        return false;
    }
}
