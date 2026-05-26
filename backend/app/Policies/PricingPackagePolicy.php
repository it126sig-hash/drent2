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
        // Superadmin selalu bisa akses semuanya
        if ($user->role === 'superadmin') return true;
        // Untuk role lain, cek apakah role tersebut diberi izin 'master.pricing_package.view' di database
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.pricing_package');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.pricing_package')
            && $user->branch_id === $pricingPackage->branch_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.pricing_package');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.pricing_package')
            && $user->branch_id === $pricingPackage->branch_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PricingPackage $pricingPackage): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, 'master.pricing_package')
            && $user->branch_id === $pricingPackage->branch_id;
    }
}
