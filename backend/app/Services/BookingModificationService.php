<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingCost;
use App\Models\CostType;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingModificationService
{
    /**
     * Extend: buat booking_detail baru type=extend dengan form lengkap (C7).
     */
    public function extend(Booking $booking, array $data): BookingDetail
    {
        $this->validateStatus($booking);

        return DB::transaction(function () use ($booking, $data) {
            $detail = $booking->bookingDetails()->create([
                'unit_id'            => $data['unit_id'],
                'driver_id'          => $data['driver_id'] ?? null,
                'tgl_sewa'           => Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s'),
                'tgl_kembali'        => Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s'),
                'lama_sewa'          => $data['lama_sewa'],
                'paket_sewa'         => $data['paket_sewa'],
                'harga_mobil'        => $data['harga_mobil'],
                'diskon_mobil'       => $data['diskon_mobil'] ?? 0,
                'pricing_mode'       => $data['pricing_mode'],
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in'       => $data['harga_all_in'] ?? null,
                'detail_type'        => 'extend',
                'status'             => 'aktif',
            ]);

            foreach ($data['costs'] ?? [] as $costData) {
                $detail->costs()->create([
                    'cost_type_id' => $costData['cost_type_id'] ?? null,
                    'type'         => $costData['type'] ?? 'biaya',
                    'label'        => $costData['label'],
                    'amount'       => $costData['amount'],
                    'keterangan'   => $costData['keterangan'] ?? null,
                ]);
            }

            return $detail;
        });
    }

    /**
     * Rolling: adjust detail lama, lalu buat detail baru type=rolling (C7).
     */
    public function rolling(Booking $booking, array $data): BookingDetail
    {
        $this->validateStatus($booking);

        return DB::transaction(function () use ($booking, $data) {
            $oldDetail = BookingDetail::findOrFail($data['booking_detail_id']);
            if ((int) $oldDetail->booking_id !== (int) $booking->id) {
                throw new UnprocessableEntityHttpException('Detail booking tidak sesuai dengan booking rolling.');
            }

            // Step 1: koreksi detail lama dan tutup di tanggal kembali hasil durasi koreksi.
            $oldDetailUpdate = [
                'unit_id'     => $data['unit_id_lama'] ?? $oldDetail->unit_id,
                'driver_id'   => $data['driver_id_lama'] ?? null,
                'tgl_kembali' => Carbon::parse($data['tgl_rolling'])->format('Y-m-d H:i:s'),
                'status'      => 'selesai',
            ];
            if (isset($data['lama_sewa_lama'])) {
                $oldDetailUpdate['lama_sewa'] = $data['lama_sewa_lama'];
            }
            if (isset($data['harga_mobil_lama'])) {
                $oldDetailUpdate['harga_mobil'] = $data['harga_mobil_lama'];
            }
            if (isset($data['diskon_mobil_lama'])) {
                $oldDetailUpdate['diskon_mobil'] = $data['diskon_mobil_lama'];
            }
            if (isset($data['paket_sewa_lama'])) {
                $oldDetailUpdate['paket_sewa'] = $data['paket_sewa_lama'];
            }
            if (isset($data['pricing_mode_lama'])) {
                $oldDetailUpdate['pricing_mode'] = $data['pricing_mode_lama'];
            }
            if (array_key_exists('pricing_package_id_lama', $data)) {
                $oldDetailUpdate['pricing_package_id'] = $data['pricing_package_id_lama'];
            }
            if (array_key_exists('harga_all_in_lama', $data)) {
                $oldDetailUpdate['harga_all_in'] = $data['harga_all_in_lama'];
            }
            $oldDetail->update($oldDetailUpdate);

            if (array_key_exists('costs_lama', $data)) {
                $oldDetail->costs()->delete();
                foreach ($data['costs_lama'] ?? [] as $costData) {
                    $oldDetail->costs()->create([
                        'cost_type_id' => $costData['cost_type_id'] ?? null,
                        'type'         => $costData['type'] ?? 'biaya',
                        'label'        => $costData['label'],
                        'amount'       => $costData['amount'],
                        'keterangan'   => $costData['keterangan'] ?? null,
                    ]);
                }
            }

            // Step 2: buat detail baru type=rolling dengan form lengkap
            $newDetail = $booking->bookingDetails()->create([
                'unit_id'            => $data['unit_id'],
                'driver_id'          => $data['driver_id'] ?? null,
                'tgl_sewa'           => Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s'),
                'tgl_kembali'        => Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s'),
                'lama_sewa'          => $data['lama_sewa'],
                'paket_sewa'         => $data['paket_sewa'],
                'harga_mobil'        => $data['harga_mobil'],
                'diskon_mobil'       => $data['diskon_mobil'] ?? 0,
                'pricing_mode'       => $data['pricing_mode'],
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in'       => $data['harga_all_in'] ?? null,
                'detail_type'        => 'rolling',
                'status'             => 'aktif',
            ]);

            foreach ($data['costs'] ?? [] as $costData) {
                $newDetail->costs()->create([
                    'cost_type_id' => $costData['cost_type_id'] ?? null,
                    'type'         => $costData['type'] ?? 'biaya',
                    'label'        => $costData['label'],
                    'amount'       => $costData['amount'],
                    'keterangan'   => $costData['keterangan'] ?? null,
                ]);
            }

            return $newDetail;
        });
    }

    /**
     * Batal: buat Refund (opsional), ubah status → batal, kembalikan unit → Aktif (C7).
     */
    public function cancel(Booking $booking, array $data): Booking
    {
        if (!in_array($booking->status, ['follow_up', 'confirm', 'waiting_list', 'rental_unit'])) {
            throw new UnprocessableEntityHttpException('Booking tidak dapat dibatalkan dari status saat ini.');
        }

        return DB::transaction(function () use ($booking, $data) {
            // Buat Refund jika ada nominal
            $refundAmount = $data['refund_amount'] ?? 0;
            if ($refundAmount > 0) {
                Refund::create([
                    'booking_id'         => $booking->id,
                    'payment_account_id' => $data['payment_account_id'] ?? null,
                    'amount'             => $refundAmount,
                    'keterangan'         => $data['refund_keterangan'] ?? null,
                    'refunded_at'        => now(),
                    'created_by'         => auth()->id(),
                ]);
            }

            // Kembalikan unit ke Aktif jika ada detail aktif
            $activeDetail = $booking->bookingDetails()
                ->whereIn('status', ['draft', 'aktif'])
                ->latest()
                ->first();

            if ($activeDetail) {
                $activeDetail->update(['status' => 'batal']);

                if ($activeDetail->unit_id) {
                    \App\Models\Unit::where('id', $activeDetail->unit_id)
                        ->update(['status' => 'Aktif']);
                }
            }

            $booking->update(['status' => 'batal']);

            return $booking->fresh();
        });
    }

    /**
     * Stop Early: selesaikan booking lebih awal dari tgl_kembali.
     * Dipertahankan untuk backward-compat dengan route stop-early.
     */
    public function stopEarly(Booking $booking, array $data): BookingDetail
    {
        $this->validateStatus($booking);

        return DB::transaction(function () use ($booking, $data) {
            $detail = BookingDetail::findOrFail($data['booking_detail_id']);

            $detail->update([
                'tgl_kembali' => Carbon::parse($data['tgl_stop'])->format('Y-m-d H:i:s'),
                'status'      => 'selesai',
            ]);

            return $detail;
        });
    }

    public function addAdditionalCost(Booking $booking, array $data): BookingCost
    {
        $this->validateStatus($booking);

        $detail = $booking->bookingDetails()
            ->where('status', 'aktif')
            ->latest()
            ->first();

        if (!$detail) {
            // Fallback to latest closed detail if no active one found
            $detail = $booking->bookingDetails()->latest()->first();
        }

        if (!$detail) {
            throw new UnprocessableEntityHttpException('Tidak ada detail booking untuk menambah biaya.');
        }

        $costType = CostType::find($data['cost_type_id']);

        return $detail->costs()->create([
            'cost_type_id' => $data['cost_type_id'] ?? null,
            'type'         => $data['type'] ?? (($data['is_discount'] ?? false) ? 'diskon' : 'biaya'),
            'label'        => $data['label'] ?? $costType?->nama ?? 'Biaya tambahan',
            'amount'       => $data['amount'],
            'keterangan'   => $data['keterangan'] ?? null,
            'is_additional' => true,
        ]);
    }

    protected function validateStatus(Booking $booking): void
    {
        if ($booking->status !== 'rental_unit') {
            throw new UnprocessableEntityHttpException('Modifikasi hanya diperbolehkan untuk booking dengan status Rental Unit.');
        }
    }
}
