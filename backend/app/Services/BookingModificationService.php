<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingCost;
use App\Models\CostType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingModificationService
{
    public function __construct(
        private BookingBillingService $billingService,
        private BookingCancellationService $cancellationService,
        private BookingModalService $modalService
    ) {
    }
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
                'modal_mobil'        => $this->modalService->resolve($data, $booking->paket_sewa),
                'diskon_mobil'       => $data['diskon_mobil'] ?? 0,
                'pricing_mode'       => $data['pricing_mode'],
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in'       => $data['harga_all_in'] ?? null,
                'detail_type'        => 'extend',
                'status'             => 'aktif',
                'created_by'         => auth()->id(),
            ]);

            foreach ($data['costs'] ?? [] as $costData) {
                $detail->costs()->create([
                    'cost_type_id' => $costData['cost_type_id'] ?? null,
                    'type'         => $costData['type'] ?? 'biaya',
                    'label'        => $costData['label'],
                    'amount'       => $costData['amount'],
                    'keterangan'   => $costData['keterangan'] ?? null,
                    'is_additional' => (bool) ($costData['is_additional'] ?? false),
                ]);
            }

            app(RentToRentService::class)->syncDetail($detail->fresh(['booking', 'unit.rentalOwner']));

            // Biaya extend memengaruhi total tagihan — sync cache
            $booking->load(['bookingDetails.costs', 'payments']);
            $this->billingService->updateCachedSisaTagihan($booking);

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
            if (array_key_exists('modal_mobil_lama', $data) && $data['modal_mobil_lama'] !== null) {
                $oldDetailUpdate['modal_mobil'] = max(0, (int) $data['modal_mobil_lama']);
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
                        'is_additional' => (bool) ($costData['is_additional'] ?? false),
                    ]);
                }
            }

            app(RentToRentService::class)->syncDetail($oldDetail->fresh(['booking', 'unit.rentalOwner']));

            // Step 2: buat detail baru type=rolling dengan form lengkap
            $newDetail = $booking->bookingDetails()->create([
                'unit_id'            => $data['unit_id'],
                'driver_id'          => $data['driver_id'] ?? null,
                'tgl_sewa'           => Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s'),
                'tgl_kembali'        => Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s'),
                'lama_sewa'          => $data['lama_sewa'],
                'paket_sewa'         => $data['paket_sewa'],
                'harga_mobil'        => $data['harga_mobil'],
                'modal_mobil'        => $this->modalService->resolve($data, $booking->paket_sewa),
                'diskon_mobil'       => $data['diskon_mobil'] ?? 0,
                'pricing_mode'       => $data['pricing_mode'],
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in'       => $data['harga_all_in'] ?? null,
                'detail_type'        => 'rolling',
                'status'             => 'aktif',
                'created_by'         => auth()->id(),
            ]);

            foreach ($data['costs'] ?? [] as $costData) {
                $newDetail->costs()->create([
                    'cost_type_id' => $costData['cost_type_id'] ?? null,
                    'type'         => $costData['type'] ?? 'biaya',
                    'label'        => $costData['label'],
                    'amount'       => $costData['amount'],
                    'keterangan'   => $costData['keterangan'] ?? null,
                    'is_additional' => (bool) ($costData['is_additional'] ?? false),
                ]);
            }

            app(RentToRentService::class)->syncDetail($newDetail->fresh(['booking', 'unit.rentalOwner']));

            // Rolling mengubah biaya — sync cache
            $booking->load(['bookingDetails.costs', 'payments']);
            $this->billingService->updateCachedSisaTagihan($booking);

            return $newDetail;
        });
    }

    /**
     * Batal: catat data pembatalan & rencana refund, ubah status → batal, kembalikan unit → Aktif (C7).
     * Pembayaran refund diproses terpisah lewat BookingCancellationService::payRefund (menu Pembatalan Booking).
     */
    public function cancel(Booking $booking, array $data): Booking
    {
        if (!in_array($booking->status, ['follow_up', 'confirm', 'waiting_list', 'rental_unit'])) {
            throw new UnprocessableEntityHttpException('Booking tidak dapat dibatalkan dari status saat ini.');
        }

        return DB::transaction(function () use ($booking, $data) {
            $this->cancellationService->create($booking, $data);

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

                app(RentToRentService::class)->syncDetail($activeDetail->fresh(['booking', 'unit.rentalOwner']));
            }

            $booking->update(['status' => 'batal']);

            $booking->bookingDetails()
                ->with(['booking', 'unit.rentalOwner'])
                ->get()
                ->each(fn(BookingDetail $detail) => app(RentToRentService::class)->syncDetail($detail));

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

            // Stop early bisa mengubah lama_sewa — sync cache
            $booking->load(['bookingDetails.costs', 'payments']);
            $this->billingService->updateCachedSisaTagihan($booking);

            return $detail;
        });
    }

    /**
     * Request pembatalan transaksi Extend — butuh approval supervisor (lihat approveCancelExtend).
     * Hanya extend terakhir yang boleh dibatalkan, sama seperti aturan edit di frontend (lastExtendDetail).
     */
    public function requestCancelExtend(BookingDetail $detail, string $reason): BookingDetail
    {
        if ($detail->detail_type !== 'extend') {
            throw new UnprocessableEntityHttpException('Hanya transaksi Extend yang bisa diajukan pembatalan.');
        }

        if ($detail->status !== 'aktif') {
            throw new UnprocessableEntityHttpException('Transaksi Extend ini sudah tidak aktif.');
        }

        $isLastExtend = !$detail->booking->bookingDetails()
            ->where('detail_type', 'extend')
            ->where('id', '>', $detail->id)
            ->exists();

        if (!$isLastExtend) {
            throw new UnprocessableEntityHttpException('Hanya transaksi Extend terakhir yang bisa dibatalkan.');
        }

        if ($detail->cancel_extend_status === 'pending') {
            throw new UnprocessableEntityHttpException('Request batal Extend masih menunggu approval supervisor.');
        }

        $detail->update([
            'cancel_extend_status' => 'pending',
            'cancel_extend_reason' => $reason,
            'cancel_extend_requested_by' => auth()->id(),
            'cancel_extend_requested_at' => now(),
            'cancel_extend_approved_by' => null,
            'cancel_extend_approved_at' => null,
            'cancel_extend_rejected_by' => null,
            'cancel_extend_rejected_at' => null,
            'cancel_extend_rejection_note' => null,
        ]);

        return $detail->fresh();
    }

    public function approveCancelExtend(BookingDetail $detail): BookingDetail
    {
        if ($detail->cancel_extend_status !== 'pending') {
            throw new UnprocessableEntityHttpException('Hanya request batal Extend berstatus pending yang bisa disetujui.');
        }

        if ($detail->cancel_extend_requested_by === auth()->id() && auth()->user()?->role !== 'superadmin') {
            throw new UnprocessableEntityHttpException('Request harus di-ACC oleh supervisor lain.');
        }

        return DB::transaction(function () use ($detail) {
            $detail->update([
                'status' => 'batal',
                'cancel_extend_status' => 'approved',
                'cancel_extend_approved_by' => auth()->id(),
                'cancel_extend_approved_at' => now(),
            ]);

            app(RentToRentService::class)->syncDetail($detail->fresh(['booking', 'unit.rentalOwner']));

            $booking = $detail->booking->load(['bookingDetails.costs', 'payments']);
            $this->billingService->updateCachedSisaTagihan($booking);

            return $detail->fresh();
        });
    }

    public function rejectCancelExtend(BookingDetail $detail, ?string $note = null): BookingDetail
    {
        if ($detail->cancel_extend_status !== 'pending') {
            throw new UnprocessableEntityHttpException('Hanya request batal Extend berstatus pending yang bisa ditolak.');
        }

        $detail->update([
            'cancel_extend_status' => 'rejected',
            'cancel_extend_rejected_by' => auth()->id(),
            'cancel_extend_rejected_at' => now(),
            'cancel_extend_rejection_note' => $note,
        ]);

        return $detail->fresh();
    }

    public function addAdditionalCost(Booking $booking, array $data): BookingCost
    {
        if (!in_array($booking->status, ['rental_unit', 'waiting_list'])) {
            throw new UnprocessableEntityHttpException('Biaya tambahan hanya bisa ditambahkan saat status Waiting List atau Rental Unit.');
        }

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
