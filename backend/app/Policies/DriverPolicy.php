<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DriverPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Driver $driver): bool
    {
        return true;
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
    public function update(User $user, Driver $driver): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Driver $driver): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    /**
     * Determine whether the user can update the balance of the driver.
     */
    public function updateBalance(User $user, Driver $driver): bool
    {
        return in_array($user->role, ['finance', 'superadmin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Driver $driver): bool
    {
        return $user->role === 'superadmin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Driver $driver): bool
    {
        return $user->role === 'superadmin';
    }
}
