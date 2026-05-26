<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    private $permission = 'vehicle.unit';

    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function view(User $user, Unit $unit): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $unit->tenant_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function update(User $user, Unit $unit): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $unit->tenant_id;
    }

    public function delete(User $user, Unit $unit): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $unit->tenant_id;
    }

    public function uploadPhoto(User $user, Unit $unit): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $unit->tenant_id;
    }
}
