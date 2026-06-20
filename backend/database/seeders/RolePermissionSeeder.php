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
                'booking.view', 'booking.create', 'booking.update', 'booking.delete', 'booking.handle', 'booking.supervisor_request', 'booking.payment',
                'physical_check.view', 'physical_check.create', 'physical_check.update',
                'finance.receivable', 'finance.receivable.create', 'finance.receivable.update',
                'finance.rent_to_rent', 'finance.rent_to_rent.create', 'finance.rent_to_rent.update',
                'finance.operational_cost', 'finance.operational_cost.create', 'finance.operational_cost.update',
                'finance.account_mutation', 'finance.account_mutation.create', 'finance.monthly_report',
                'finance.invoice_terms', 'finance.invoice_terms.create', 'finance.invoice_terms.update', 'finance.invoice_terms.delete',
                'vehicle.rental_owner', 'vehicle.rental_owner.create', 'vehicle.rental_owner.update', 'vehicle.rental_owner.delete',
                'vehicle.unit', 'vehicle.unit.create', 'vehicle.unit.update', 'vehicle.unit.delete',
                'vehicle.driver', 'vehicle.driver.create', 'vehicle.driver.update', 'vehicle.driver.delete',
                'customer.view', 'customer.create', 'customer.update', 'customer.delete',
                'member.view', 'member.create', 'member.update', 'member.delete',
                'master.user', 'master.user.create', 'master.user.update', 'master.user.delete',
                'master.payment_account', 'master.payment_account.create', 'master.payment_account.update', 'master.payment_account.delete',
                'master.city', 'master.city.create', 'master.city.update', 'master.city.delete',
                'master.cost_type', 'master.cost_type.create', 'master.cost_type.update', 'master.cost_type.delete',
                'master.pricing_package', 'master.pricing_package.create', 'master.pricing_package.update', 'master.pricing_package.delete',
                'master.finance_category', 'master.finance_category.create', 'master.finance_category.update', 'master.finance_category.delete',
                'master.role_management', 'master.tenant',
                'master.branch', 'master.branch.create', 'master.branch.update', 'master.branch.delete'
            ],
            'admin_branch' => [
                'dashboard.view',
                'booking.view', 'booking.create', 'booking.update', 'booking.handle', 'booking.payment',
                'physical_check.view', 'physical_check.create', 'physical_check.update',
                'finance.receivable', 'finance.receivable.create', 'finance.receivable.update',
                'finance.rent_to_rent', 'finance.rent_to_rent.create', 'finance.rent_to_rent.update',
                'finance.operational_cost', 'finance.operational_cost.create', 'finance.operational_cost.update',
                'finance.account_mutation', 'finance.account_mutation.create', 'finance.monthly_report', 'finance.transaction',
                'finance.invoice_terms', 'finance.invoice_terms.create', 'finance.invoice_terms.update', 'finance.invoice_terms.delete',
                'vehicle.rental_owner', 'vehicle.rental_owner.create', 'vehicle.rental_owner.update', 'vehicle.rental_owner.delete',
                'vehicle.unit', 'vehicle.unit.create', 'vehicle.unit.update', 'vehicle.unit.delete',
                'vehicle.driver', 'vehicle.driver.create', 'vehicle.driver.update', 'vehicle.driver.delete',
                'customer.view', 'customer.create', 'customer.update', 'customer.delete',
                'member.view', 'member.create', 'member.update', 'member.delete',
                'master.user', 'master.user.create', 'master.user.update', 'master.user.delete',
                'master.payment_account', 'master.payment_account.create', 'master.payment_account.update', 'master.payment_account.delete',
                'master.city', 'master.city.create', 'master.city.update', 'master.city.delete',
                'master.cost_type', 'master.cost_type.create', 'master.cost_type.update', 'master.cost_type.delete',
                'master.pricing_package', 'master.pricing_package.create', 'master.pricing_package.update', 'master.pricing_package.delete',
                'master.finance_category', 'master.finance_category.create', 'master.finance_category.update', 'master.finance_category.delete',
                'master.role_management',
                'master.branch', 'master.branch.update'
            ],
            'finance' => [
                'dashboard.view',
                'booking.view',
                'finance.receivable', 'finance.receivable.create', 'finance.receivable.update',
                'finance.rent_to_rent', 'finance.rent_to_rent.create', 'finance.rent_to_rent.update',
                'finance.operational_cost', 'finance.operational_cost.create', 'finance.operational_cost.update',
                'finance.account_mutation', 'finance.account_mutation.create', 'finance.monthly_report', 'finance.transaction',
                'vehicle.driver',
                'master.payment_account', 'master.payment_account.create', 'master.payment_account.update',
                'master.finance_category', 'master.finance_category.create'
            ],
            'cs' => [
                'dashboard.view',
                'booking.view', 'booking.create', 'booking.handle',
                'physical_check.view', 'physical_check.create', 'physical_check.update',
                'vehicle.unit',
                'customer.view', 'customer.create', 'customer.update', 'customer.delete',
                'member.view', 'member.create', 'member.update', 'member.delete',
                'master.city', 'master.city.create', 'master.city.update', 'master.city.delete'
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
