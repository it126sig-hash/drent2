<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        $branch = \App\Models\Branch::where('tenant_id', $tenant->id)->first();
        $customers = \App\Models\Customer::all();
        $units = \App\Models\Unit::all();
        $drivers = \App\Models\Driver::all();

        if ($customers->isEmpty() || $units->isEmpty()) {
            return;
        }

        $statuses = ['follow_up', 'confirm', 'waiting_list'];

        foreach (range(1, 10) as $i) {
            $status = $statuses[array_rand($statuses)];
            $customer = $customers->random();
            $unit = $units->random();
            $driver = $drivers->isEmpty() ? null : $drivers->random();

            $booking = \App\Models\Booking::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'kode_booking' => 'BK-' . date('Ym') . '-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'status' => $status,
                'harga_dealing' => 500000 * rand(1, 5),
                'dp' => $status === 'confirm' ? 100000 * rand(1, 5) : null,
                'rekening_dp_id' => $status === 'confirm' ? 1 : null,
                'tujuan' => 'Tujuan Wisata ' . $i,
                'alamat_penjemputan' => 'Alamat ' . $i,
                'catatan' => 'Catatan booking ' . $i,
            ]);

            $detail = \App\Models\BookingDetail::create([
                'booking_id' => $booking->id,
                'unit_id' => rand(0, 1) ? $unit->id : null,
                'unit_placeholder' => rand(0, 1) ? null : 'Avanza Hitam ' . $i,
                'driver_id' => $driver?->id,
                'tgl_sewa' => now()->addDays(rand(1, 5))->format('Y-m-d'),
                'tgl_kembali' => now()->addDays(rand(6, 10))->format('Y-m-d'),
                'harga_mobil' => 350000,
                'diskon_mobil' => 0,
                'detail_type' => 'initial',
                'status' => 'draft',
            ]);

            if ($status === 'confirm') {
                \App\Models\BookingCost::create([
                    'booking_detail_id' => $detail->id,
                    'type' => 'biaya',
                    'label' => 'Biaya Driver',
                    'amount' => 150000,
                ]);
            }
        }
    }
}
