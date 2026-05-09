<?php

namespace App\Policies;

use App\Models\RentalOwner;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RentalOwnerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Semua role yang login bisa lihat list
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RentalOwner $rentalOwner): bool
    {
        return $user->tenant_id === $rentalOwner->tenant_id;
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
    public function update(User $user, RentalOwner $rentalOwner): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']) && $user->tenant_id === $rentalOwner->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RentalOwner $rentalOwner): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']) && $user->tenant_id === $rentalOwner->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RentalOwner $rentalOwner): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']) && $user->tenant_id === $rentalOwner->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RentalOwner $rentalOwner): bool
    {
        return $user->role === 'superadmin';
    }
}
