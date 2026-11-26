<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds all Indonesian regencies (kabupaten/kota) from the Kemendagri 2025
 * dataset (database/seeders/data/regencies.csv: id, province_code, name)
 * and links them to the provinces seeded by ProvinceSeeder.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::query()->oldest('id')->value('id')
            ?? Tenant::create(['name' => 'DRENT Global', 'slug' => 'drent-global', 'is_active' => true])->id;

        $provinceIds = Province::pluck('id', 'nama');

        $handle = fopen(__DIR__.'/data/regencies.csv', 'r');
        fgetcsv($handle); // header

        while (($row = fgetcsv($handle)) !== false) {
            [$kode, $provinceCode, $nama] = $row;

            $provinceId = $this->provinceIdFromCode($provinceIds, $provinceCode);
            if ($provinceId === null) {
                continue;
            }

            City::withTrashed()->updateOrCreate(
                ['tenant_id' => $tenantId, 'nama' => $nama],
                ['province_id' => $provinceId, 'provinsi' => $this->provinceName($provinceCode), 'is_active' => true]
            );
        }

        fclose($handle);
    }

    /**
     * The CSV uses Kemendagri codes ("11".."96") while provinces are seeded
     * by name; map via the official code-to-name order of Kepmendagri 2025.
     */
    private function provinceIdFromCode($provinceIds, string $code): ?int
    {
        static $codeToName = [
            11 => 'Aceh', 12 => 'Sumatera Utara', 13 => 'Sumatera Barat',
            14 => 'Riau', 15 => 'Jambi', 16 => 'Sumatera Selatan',
            17 => 'Bengkulu', 18 => 'Lampung', 19 => 'Kepulauan Bangka Belitung',
            21 => 'Kepulauan Riau', 31 => 'DKI Jakarta', 32 => 'Jawa Barat',
            33 => 'Jawa Tengah', 34 => 'DI Yogyakarta', 35 => 'Jawa Timur',
            36 => 'Banten', 51 => 'Bali', 52 => 'Nusa Tenggara Barat',
            53 => 'Nusa Tenggara Timur', 61 => 'Kalimantan Barat',
            62 => 'Kalimantan Tengah', 63 => 'Kalimantan Selatan',
            64 => 'Kalimantan Timur', 65 => 'Kalimantan Utara',
            71 => 'Sulawesi Utara', 72 => 'Sulawesi Tengah',
            73 => 'Sulawesi Selatan', 74 => 'Sulawesi Tenggara',
            75 => 'Gorontalo', 76 => 'Sulawesi Barat', 81 => 'Maluku',
            82 => 'Maluku Utara', 91 => 'Papua', 92 => 'Papua Barat',
            93 => 'Papua Selatan', 94 => 'Papua Tengah',
            95 => 'Papua Pegunungan', 96 => 'Papua Barat Daya',
        ];

        $nama = $codeToName[(int) $code] ?? null;

        return $nama !== null ? $provinceIds->get($nama) : null;
    }

    private function provinceName(string $code): ?string
    {
        $names = [
            11 => 'Aceh', 12 => 'Sumatera Utara', 13 => 'Sumatera Barat',
            14 => 'Riau', 15 => 'Jambi', 16 => 'Sumatera Selatan',
            17 => 'Bengkulu', 18 => 'Lampung', 19 => 'Kepulauan Bangka Belitung',
            21 => 'Kepulauan Riau', 31 => 'DKI Jakarta', 32 => 'Jawa Barat',
            33 => 'Jawa Tengah', 34 => 'DI Yogyakarta', 35 => 'Jawa Timur',
            36 => 'Banten', 51 => 'Bali', 52 => 'Nusa Tenggara Barat',
            53 => 'Nusa Tenggara Timur', 61 => 'Kalimantan Barat',
            62 => 'Kalimantan Tengah', 63 => 'Kalimantan Selatan',
            64 => 'Kalimantan Timur', 65 => 'Kalimantan Utara',
            71 => 'Sulawesi Utara', 72 => 'Sulawesi Tengah',
            73 => 'Sulawesi Selatan', 74 => 'Sulawesi Tenggara',
            75 => 'Gorontalo', 76 => 'Sulawesi Barat', 81 => 'Maluku',
            82 => 'Maluku Utara', 91 => 'Papua', 92 => 'Papua Barat',
            93 => 'Papua Selatan', 94 => 'Papua Tengah',
            95 => 'Papua Pegunungan', 96 => 'Papua Barat Daya',
        ];

        return $names[(int) $code] ?? null;
    }
}
