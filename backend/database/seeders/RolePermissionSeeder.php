<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\RolePermission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            return;
        }

        $roles = [
            'superadmin' => [
                'dashboard.view',
                'booking.view', 'booking.create', 'booking.handle', 'booking.supervisor_request',
                'physical_check.view',
                'finance.receivable', 'finance.rent_to_rent', 'finance.operational_cost', 'finance.account_mutation', 'finance.monthly_report',
                'vehicle.rental_owner', 'vehicle.unit', 'vehicle.driver',
                'customer.view', 'member.view',
                'master.user', 'master.payment_account', 'master.city', 'master.cost_type', 'master.pricing_package', 'master.role_management'
            ],
            'admin_branch' => [
                'dashboard.view',
                'booking.view', 'booking.create', 'booking.handle',
                'physical_check.view',
                'finance.receivable', 'finance.rent_to_rent', 'finance.operational_cost', 'finance.account_mutation', 'finance.monthly_report',
                'vehicle.rental_owner', 'vehicle.unit', 'vehicle.driver',
                'customer.view', 'member.view',
                'master.user', 'master.payment_account', 'master.city', 'master.cost_type', 'master.pricing_package', 'master.role_management'
            ],
            'finance' => [
                'dashboard.view',
                'booking.view',
                'finance.receivable', 'finance.rent_to_rent', 'finance.operational_cost', 'finance.account_mutation', 'finance.monthly_report',
                'vehicle.driver',
                'master.payment_account'
            ],
            'cs' => [
                'dashboard.view',
                'booking.view', 'booking.create', 'booking.handle',
                'physical_check.view',
                'vehicle.unit',
                'customer.view', 'member.view',
                'master.city'
            ],
            'supervisor' => [
                'dashboard.view',
                'booking.view', 'booking.supervisor_request'
            ],
            'driver_tetap' => [
                'dashboard.view',
                'driver.operational'
            ],
            'teknisi' => [
                'dashboard.view',
                'booking.view',
                'vehicle.unit'
            ]
        ];

        foreach ($roles as $role => $permissions) {
            foreach ($permissions as $permission) {
                RolePermission::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'role' => $role,
                        'permission_key' => $permission
                    ]
                );
            }
        }
    }
}
