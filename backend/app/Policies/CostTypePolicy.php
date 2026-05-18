<?php

namespace App\Policies;

use App\Models\CostType;
use App\Models\User;

class CostTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'finance', 'driver_tetap']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CostType $costType): bool
    {
        if ($user->role === 'superadmin') return true;
        return $user->tenant_id === $costType->tenant_id;
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
    public function update(User $user, CostType $costType): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CostType $costType): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }
}
