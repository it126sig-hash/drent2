<?php

namespace App\Policies;

use App\Models\City;
use App\Models\User;

class CityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, City $city): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        return $user->tenant_id === $city->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'cs'], true);
    }

    public function update(User $user, City $city): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch', 'cs'], true)
            && ($user->role === 'superadmin' || $user->tenant_id === $city->tenant_id);
    }

    public function delete(User $user, City $city): bool
    {
        return in_array($user->role, ['superadmin', 'admin_branch'], true)
            && ($user->role === 'superadmin' || $user->tenant_id === $city->tenant_id);
    }
}
