<?php

namespace App\Services;

use App\Models\RolePermission;
use App\Models\UserPermissionOverride;
use App\Models\User;

class PermissionService
{
    /**
     * Canonical list of all permission keys defined in the system.
     * Update this list whenever a new permission is added to the frontend
     * permission definitions (permissionDefinitions in RolePermissionView.vue).
     */
    public const ALL_PERMISSION_KEYS = [
        'dashboard.view',
        'booking.view',
        'booking.create',
        'booking.handle',
        'booking.supervisor_request',
        'physical_check.view',
        'finance.receivable',
        'finance.rent_to_rent',
        'finance.operational_cost',
        'finance.account_mutation',
        'finance.monthly_report',
        'finance.transaction',
        'vehicle.rental_owner',
        'vehicle.unit',
        'vehicle.driver',
        'driver.operational',
        'customer.view',
        'member.view',
        'master.user',
        'master.payment_account',
        'master.city',
        'master.cost_type',
        'master.pricing_package',
        'master.role_management',
    ];

    /**
     * Get all effective permissions for a user.
     *
     * @param User $user
     * @return array
     */
    public function getEffectivePermissions(User $user): array
    {
        // Superadmin bypasses all checks — return the full canonical permission list.
        // We use a constant instead of querying DB so this works even when
        // role_permissions table is empty (e.g., fresh install or empty seed).
        if ($user->role === 'superadmin') {
            return self::ALL_PERMISSION_KEYS;
        }

        // Get default permissions for the user's role
        $rolePermissions = RolePermission::where('tenant_id', $user->tenant_id)
            ->where('role', $user->role)
            ->pluck('permission_key')
            ->toArray();

        // Get overrides for the user
        $overrides = UserPermissionOverride::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->get();

        $granted = $overrides->where('value', 'grant')->pluck('permission_key')->toArray();
        $revoked = $overrides->where('value', 'revoke')->pluck('permission_key')->toArray();

        // Apply overrides
        $effective = array_merge($rolePermissions, $granted);
        $effective = array_diff($effective, $revoked);

        return array_values(array_unique($effective));
    }

    /**
     * Check if a user has a specific permission.
     *
     * @param User $user
     * @param string $key
     * @return bool
     */
    public function hasPermission(User $user, string $key): bool
    {
        // Superadmin always has all permissions — no DB query needed.
        if ($user->role === 'superadmin') {
            return true;
        }

        $effective = $this->getEffectivePermissions($user);
        return in_array($key, $effective);
    }
}
