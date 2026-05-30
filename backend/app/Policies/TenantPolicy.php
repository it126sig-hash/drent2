<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Hanya superadmin yang dapat melihat profil tenant miliknya sendiri.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->role === 'superadmin'
            && $user->tenant_id === $tenant->id;
    }

    /**
     * Hanya superadmin yang dapat update profil tenant miliknya sendiri.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->role === 'superadmin'
            && $user->tenant_id === $tenant->id;
    }
}
