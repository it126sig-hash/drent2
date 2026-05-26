<?php

namespace App\Policies;

use App\Models\RentalOwner;
use App\Models\User;

class RentalOwnerPolicy
{
    private $permission = 'vehicle.rental_owner';

    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function view(User $user, RentalOwner $rentalOwner): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $rentalOwner->tenant_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function update(User $user, RentalOwner $rentalOwner): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $rentalOwner->tenant_id;
    }

    public function delete(User $user, RentalOwner $rentalOwner): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $rentalOwner->tenant_id;
    }

    public function restore(User $user, RentalOwner $rentalOwner): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $rentalOwner->tenant_id;
    }

    public function forceDelete(User $user, RentalOwner $rentalOwner): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $rentalOwner->tenant_id;
    }
}
