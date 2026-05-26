<?php

namespace App\Policies;

use App\Models\User;

class RolePermissionPolicy
{
    public function manage(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch']);
    }

    public function updateRole(User $user, string $targetRole): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        // admin_branch cannot modify superadmin or other admin_branch roles
        if ($user->role === 'admin_branch') {
            return !in_array($targetRole, ['superadmin', 'admin_branch']);
        }

        return false;
    }
}
