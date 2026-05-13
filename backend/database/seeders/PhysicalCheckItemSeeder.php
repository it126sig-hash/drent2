<?php

namespace Database\Seeders;

use App\Models\PhysicalCheckItem;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PhysicalCheckItemSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            return;
        }

        $items = [
            ['name' => 'Ban serep', 'sort_order' => 1],
            ['name' => 'Dongkrak', 'sort_order' => 2],
            ['name' => 'Toolkit', 'sort_order' => 3],
            ['name' => 'Segitiga pengaman', 'sort_order' => 4],
            ['name' => 'STNK', 'sort_order' => 5],
            ['name' => 'Kunci roda', 'sort_order' => 6],
            ['name' => 'Karpet kabin', 'sort_order' => 7],
            ['name' => 'Buku manual/servis', 'sort_order' => 8],
        ];

        foreach ($items as $item) {
            PhysicalCheckItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => Str::slug($item['name']),
                ],
                [
                    'name' => $item['name'],
                    'is_required' => true,
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ]
            );
        }
    }
}
