<?php

namespace App\Policies;

use App\Models\PricingPackage;
use App\Models\User;

class PricingPackagePolicy
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
    public function view(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return $user->branch_id === $pricingPackage->branch_id;
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
    public function update(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return $user->role === 'admin_branch' && $user->branch_id === $pricingPackage->branch_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return $user->role === 'admin_branch' && $user->branch_id === $pricingPackage->branch_id;
    }
}
