<?php

namespace App\Policies;

use App\Models\CostType;
use App\Models\User;

class CostTypePolicy
{
    private $permission = 'master.cost_type';

    public function viewAny(User $user): bool
    {
        // if ($user->role === 'superadmin') return true;
        // return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
        return true;
    }

    public function view(User $user, CostType $costType): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $costType->tenant_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function update(User $user, CostType $costType): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $costType->tenant_id;
    }

    public function delete(User $user, CostType $costType): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->tenant_id === $costType->tenant_id;
    }
}
