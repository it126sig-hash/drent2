<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CostTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();

        $types = [
            ['nama' => 'Driver',        'kode' => 'driver',        'require_description' => false, 'sort_order' => 1],
            ['nama' => 'BBM',           'kode' => 'bbm',           'require_description' => false, 'sort_order' => 2],
            ['nama' => 'Tol',           'kode' => 'tol',           'require_description' => false, 'sort_order' => 3],
            ['nama' => 'Uang Makan',    'kode' => 'uang-makan',    'require_description' => false, 'sort_order' => 4],
            ['nama' => 'Penginapan',    'kode' => 'penginapan',    'require_description' => false, 'sort_order' => 5],
            ['nama' => 'Parkir',        'kode' => 'parkir',        'require_description' => false, 'sort_order' => 6],
            ['nama' => 'Antar Jemput',  'kode' => 'antar-jemput',  'require_description' => false, 'sort_order' => 7],
            ['nama' => 'Lainnya',       'kode' => 'lainnya',       'require_description' => true,  'sort_order' => 99],
        ];

        foreach ($types as $type) {
            \App\Models\CostType::create(array_merge($type, [
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]));
        }
    }
}
