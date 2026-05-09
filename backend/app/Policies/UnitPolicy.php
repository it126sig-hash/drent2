<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance', 'cs', 'teknisi']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Unit $unit): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance', 'cs', 'teknisi']) 
            && $user->tenant_id === $unit->tenant_id;
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
    public function update(User $user, Unit $unit): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']) 
            && $user->tenant_id === $unit->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']) 
            && $user->tenant_id === $unit->tenant_id;
    }

    /**
     * Determine whether the user can upload photos to the model.
     */
    public function uploadPhoto(User $user, Unit $unit): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'teknisi']) 
            && $user->tenant_id === $unit->tenant_id;
    }
}
