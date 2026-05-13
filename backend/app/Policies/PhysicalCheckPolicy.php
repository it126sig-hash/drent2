<?php

namespace App\Policies;

use App\Models\PhysicalCheck;
use App\Models\User;

class PhysicalCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'cs']);
    }

    public function view(User $user, PhysicalCheck $physicalCheck): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $physicalCheck->branch_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'cs']);
    }

    public function update(User $user, PhysicalCheck $physicalCheck): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return in_array($user->role, ['admin_branch', 'cs'])
            && $user->branch_id === $physicalCheck->branch_id;
    }
}
