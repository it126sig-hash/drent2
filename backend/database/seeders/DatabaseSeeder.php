<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'DRENT Global',
            'slug' => 'drent-global',
            'is_active' => true,
        ]);

        $branch = \App\Models\Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pusat Jakarta',
            'address' => 'Jl. Kebagusan Raya No. 1, Jakarta Selatan',
            'phone' => '021-12345678',
        ]);

        \App\Models\User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Super Admin DRENT',
            'email' => 'admin@drent.id',
            'password' => bcrypt('admin123'),
            'role' => 'superadmin',
        ]);

        $this->call([
            BookingSeeder::class,
        ]);
    }
}
