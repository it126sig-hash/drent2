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
        // Dashboard
        'dashboard.view',

        // Booking
        'booking.view',
        'booking.create',
        'booking.update',
        'booking.delete',
        'booking.handle',
        'booking.supervisor_request',
        'booking.payment',

        // Physical Check
        'physical_check.view',
        'physical_check.create',
        'physical_check.update',

        // Finance - Piutang
        'finance.receivable',
        'finance.receivable.create',
        'finance.receivable.update',

        // Finance - Rent to Rent
        'finance.rent_to_rent',
        'finance.rent_to_rent.create',
        'finance.rent_to_rent.update',

        // Finance - Biaya Operasional
        'finance.operational_cost',
        'finance.operational_cost.create',
        'finance.operational_cost.update',

        // Finance - Mutasi Rekening
        'finance.account_mutation',
        'finance.account_mutation.create',

        // Finance - Laporan
        'finance.monthly_report',
        'finance.transaction',

        // Finance - Template Invoice
        'finance.invoice_terms',
        'finance.invoice_terms.create',
        'finance.invoice_terms.update',
        'finance.invoice_terms.delete',

        // Kendaraan - Pemilik Rental
        'vehicle.rental_owner',
        'vehicle.rental_owner.create',
        'vehicle.rental_owner.update',
        'vehicle.rental_owner.delete',

        // Kendaraan - Unit
        'vehicle.unit',
        'vehicle.unit.create',
        'vehicle.unit.update',
        'vehicle.unit.delete',

        // Kendaraan - Driver
        'vehicle.driver',
        'vehicle.driver.create',
        'vehicle.driver.update',
        'vehicle.driver.delete',

        // Driver Operasional
        'driver.operational',

        // Pelanggan
        'customer.view',
        'customer.create',
        'customer.update',
        'customer.delete',

        // Member
        'member.view',
        'member.create',
        'member.update',
        'member.delete',

        // Master - User
        'master.user',
        'master.user.create',
        'master.user.update',
        'master.user.delete',

        // Master - Akun Pembayaran
        'master.payment_account',
        'master.payment_account.create',
        'master.payment_account.update',
        'master.payment_account.delete',

        // Master - Kota
        'master.city',
        'master.city.create',
        'master.city.update',
        'master.city.delete',

        // Master - Tipe Biaya
        'master.cost_type',
        'master.cost_type.create',
        'master.cost_type.update',
        'master.cost_type.delete',

        // Master - Paket Harga
        'master.pricing_package',
        'master.pricing_package.create',
        'master.pricing_package.update',
        'master.pricing_package.delete',

        // Master - Kategori Keuangan (baru)
        'master.finance_category',
        'master.finance_category.create',
        'master.finance_category.update',
        'master.finance_category.delete',

        // Master - Cabang
        'master.branch',
        'master.branch.create',
        'master.branch.update',
        'master.branch.delete',

        // Master - Tenant & Role
        'master.tenant',
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
