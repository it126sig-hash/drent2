<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            'Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Jambi',
            'Sumatera Selatan', 'Bengkulu', 'Lampung', 'Kepulauan Bangka Belitung',
            'Kepulauan Riau', 'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah',
            'DI Yogyakarta', 'Jawa Timur', 'Banten', 'Bali', 'Nusa Tenggara Barat',
            'Nusa Tenggara Timur', 'Kalimantan Barat', 'Kalimantan Tengah',
            'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara',
            'Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan',
            'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat', 'Maluku',
            'Maluku Utara', 'Papua', 'Papua Barat', 'Papua Selatan',
            'Papua Tengah', 'Papua Pegunungan', 'Papua Barat Daya',
        ];

        foreach ($provinces as $nama) {
            Province::firstOrCreate(['nama' => $nama]);
        }
    }
}
