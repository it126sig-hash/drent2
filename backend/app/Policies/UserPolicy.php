<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private $permission = 'master.user';

    public function viewAny(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $model->branch_id;
    }

    public function create(User $user): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // cannot edit self in MDM
        }

        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $model->branch_id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false; // cannot delete self
        }

        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $model->branch_id;
    }

    public function resetPassword(User $user, User $model): bool
    {
        if ($user->role === 'superadmin') return true;
        return app(\App\Services\PermissionService::class)->hasPermission($user, $this->permission)
            && $user->branch_id === $model->branch_id;
    }
}
