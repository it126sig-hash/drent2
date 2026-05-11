<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PricingPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        $branch = \App\Models\Branch::where('tenant_id', $tenant->id)->first();

        $packages = [
            [
                'nama_paket'  => 'All In Avanza Bandung',
                'harga'       => 750000,
                'keterangan'  => 'Include: driver, BBM, tol Cipularang PP',
            ],
            [
                'nama_paket'  => 'All In Innova Jakarta',
                'harga'       => 1200000,
                'keterangan'  => 'Include: driver, BBM, tol dalam kota',
            ],
            [
                'nama_paket'  => 'All In Hiace Semarang',
                'harga'       => 2500000,
                'keterangan'  => 'Include: driver, BBM, tol, uang makan driver, parkir',
            ],
        ];

        foreach ($packages as $pkg) {
            \App\Models\PricingPackage::create(array_merge($pkg, [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]));
        }
    }
}
