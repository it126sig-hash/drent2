<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PaymentAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        $branches = \App\Models\Branch::where('tenant_id', $tenant->id)->get();

        $accounts = [
            ['nama_bank' => 'BCA',     'nomor_rekening' => '1234567890', 'atas_nama' => 'PT DRENT Global'],
            ['nama_bank' => 'Mandiri', 'nomor_rekening' => '0987654321', 'atas_nama' => 'PT DRENT Global'],
            ['nama_bank' => 'Cash',    'nomor_rekening' => '-',          'atas_nama' => 'Kasir'],
        ];

        foreach ($branches as $branch) {
            foreach ($accounts as $account) {
                \App\Models\PaymentAccount::create(array_merge($account, [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]));
            }
        }
    }
}
