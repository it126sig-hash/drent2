<?php

namespace App\Services;

use App\Exceptions\BookingBlacklistException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingCost;
use App\Models\BookingPayment;
use App\Models\Customer;
use App\Models\RentalOwner;
use App\Traits\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    use PhoneNormalizer;

    public function createBooking(array $data, int $branchId, int $tenantId): Booking
    {
        return DB::transaction(function () use ($data, $branchId, $tenantId) {
            $customerId = $data['customer_id'] ?? null;

            // 1. If rental owner is selected as renter, mirror it into customers.
            if (!$customerId && isset($data['rental_owner_id'])) {
                $rentalOwner = RentalOwner::where('tenant_id', $tenantId)
                    ->findOrFail($data['rental_owner_id']);
                $normalizedPhone = $this->normalizePhone($rentalOwner->kontak_1);

                $customer = Customer::where('tenant_id', $tenantId)
                    ->where('kontak_1', $normalizedPhone)
                    ->first();

                if (!$customer) {
                    $customer = Customer::create([
                        'tenant_id' => $tenantId,
                        'nama' => $rentalOwner->nama,
                        'kontak_1' => $normalizedPhone,
                        'kontak_2' => $rentalOwner->kontak_2,
                        'email' => $rentalOwner->email ?? null,
                        'alamat' => $rentalOwner->alamat,
                        'kota' => $rentalOwner->kota ?? '-',
                        'status' => 'Rent to Rent',
                    ]);
                } elseif (!in_array($customer->status, ['Redflag', 'Blacklist'], true)) {
                    $customer->update(['status' => 'Rent to Rent']);
                }

                $customerId = $customer->id;
            }

            // 2. If new customer, create one
            if (!$customerId) {
                $normalizedPhone = $this->normalizePhone($data['customer_phone']);
                
                // Check if already exists (as per plan's requirement in StoreBookingRequest, 
                // but we handle here too for safety)
                $existingCustomer = Customer::where('kontak_1', $normalizedPhone)->first();
                
                if ($existingCustomer) {
                    $customerId = $existingCustomer->id;
                } else {
                    $customer = Customer::create([
                        'tenant_id' => $tenantId,
                        'nama' => $data['customer_name'],
                        'kontak_1' => $normalizedPhone,
                        'email' => $data['customer_email'] ?? null,
                        'kota' => $data['customer_city'] ?? '-',
                        'status' => $this->normalizeCustomerStatus($data['customer_status'] ?? 'Normal'),
                    ]);
                    $customerId = $customer->id;
                }
            }

            // 3. Check if customer is blacklisted
            $customer = Customer::findOrFail($customerId);
            if ($customer->status === 'Blacklist') {
                throw new BookingBlacklistException("Pelanggan diblacklist. Booking tidak bisa dilanjutkan.");
            }

            // 4. Set status based on DP
            $hasDp = isset($data['dp']) && $data['dp'] > 0;
            $status = $hasDp ? 'confirm' : 'follow_up';

            // 5. Generate booking code: BK-YYYYMM-XXXXX
            $prefix = 'BK-' . date('Ym') . '-';
            $lastBooking = Booking::where('branch_id', $branchId)
                ->where('kode_booking', 'like', $prefix . '%')
                ->orderBy('kode_booking', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastBooking) {
                $lastNumber = (int) substr($lastBooking->kode_booking, -5);
                $nextNumber = $lastNumber + 1;
            }
            $kodeBooking = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            // 6. Create Booking
            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'created_by' => auth()->id(),
                'kode_booking' => $kodeBooking,
                'status' => $status,
                'lama_sewa' => $data['lama_sewa'] ?? null,
                'paket_sewa' => $data['paket_sewa'] ?? null,
                'harga_dealing' => $data['harga_dealing'] ?? null,
                'dp' => $data['dp'] ?? null,
                'rekening_dp_id' => $data['rekening_dp_id'] ?? null,
                'tujuan' => $data['tujuan'] ?? null,
                'kota' => $data['kota'] ?? null,
                'alamat_penjemputan' => $data['alamat_penjemputan'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'confirmed_by' => $hasDp ? auth()->id() : null,
                'confirmed_at' => $hasDp ? now() : null,
            ]);

            // 7. Create Booking Detail
            BookingDetail::create([
                'booking_id' => $booking->id,
                'unit_id' => $data['unit_id'] ?? null,
                'unit_placeholder' => $data['unit_placeholder'] ?? null,
                'driver_id' => null,
                'tgl_sewa' => Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s'),
                'tgl_kembali' => Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s'),
                'harga_mobil' => null,
                'diskon_mobil' => 0,
                'lama_sewa' => $data['lama_sewa'] ?? null,
                'paket_sewa' => $data['paket_sewa'] ?? null,
                'detail_type' => 'initial',
                'status' => 'draft',
            ]);

            // 8. Create BookingPayment record for DP
            if ($hasDp) {
                $dpPaidAt = isset($data['dp_paid_at'])
                    ? Carbon::parse($data['dp_paid_at'])->format('Y-m-d H:i:s')
                    : now();

                BookingPayment::create([
                    'booking_id' => $booking->id,
                    'payment_account_id' => $data['rekening_dp_id'],
                    'amount' => $data['dp'],
                    'payment_type' => 'dp',
                    'catatan' => 'DP saat pembuatan booking',
                    'paid_at' => $dpPaidAt,
                    'created_by' => auth()->id(),
                ]);
            }

            return $booking->load(['customer', 'createdBy', 'confirmedBy', 'bookingDetails.unit.rentalOwner', 'payments.creator']);
        });
    }

    /**
     * Assign a unit and driver to a booking.
     */
    public function assignDetail(Booking $booking, array $data): BookingDetail
    {
        return DB::transaction(function () use ($booking, $data) {
            $detail = $booking->bookingDetails()->create([
                'unit_id' => $data['unit_id'],
                'driver_id' => $data['driver_id'] ?? null,
                'tgl_sewa' => Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s'),
                'tgl_kembali' => Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s'),
                'harga_mobil' => $data['harga_mobil'],
                'diskon_mobil' => $data['diskon_mobil'] ?? 0,
                'lama_sewa' => $data['lama_sewa'] ?? $booking->lama_sewa,
                'paket_sewa' => $data['paket_sewa'] ?? $booking->paket_sewa,
                'pricing_mode' => $data['pricing_mode'] ?? 'non_all_in',
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in' => $data['harga_all_in'] ?? null,
                'detail_type' => $data['detail_type'] ?? 'initial',
                'status' => 'draft',
            ]);

            foreach ($data['costs'] ?? [] as $costData) {
                $detail->costs()->create([
                    'cost_type_id' => $costData['cost_type_id'] ?? null,
                    'type' => $costData['type'] ?? 'biaya',
                    'label' => $costData['label'],
                    'amount' => $costData['amount'],
                    'keterangan' => $costData['keterangan'] ?? null,
                    'is_additional' => (bool) ($costData['is_additional'] ?? false),
                ]);
            }

            app(RentToRentService::class)->syncDetail($detail);

            return $detail;
        });
    }

    /**
     * Add an operational cost to a booking detail.
     */
    public function addCost(BookingDetail $detail, array $data): BookingCost
    {
        return $detail->costs()->create([
            'type' => $data['type'],
            'label' => $data['label'],
            'amount' => $data['amount'],
        ]);
    }

    /**
     * Handle a booking: fill in detail, costs, and move status to waiting_list (C4).
     */
    public function handleBooking(Booking $booking, array $data): Booking
    {
        if (!in_array($booking->status, ['follow_up', 'confirm'])) {
            throw new \InvalidArgumentException(
                "Handle hanya diperbolehkan untuk booking dengan status follow_up atau confirm."
            );
        }

        return DB::transaction(function () use ($booking, $data) {
            // 1. Update booking-level fields
            $booking->update([
                'lama_sewa'          => $data['lama_sewa'],
                'paket_sewa'         => $data['paket_sewa'],
                'alamat_penjemputan' => $data['alamat_penjemputan'] ?? $booking->alamat_penjemputan,
                'tujuan'             => $data['tujuan'] ?? $booking->tujuan,
                'kota'               => $data['kota'] ?? $booking->kota,
                'status'             => 'waiting_list',
                'handled_by'         => $booking->handled_by ?? auth()->id(),
                'handled_at'         => $booking->handled_at ?? now(),
            ]);

            // 2. Update the initial booking_detail (draft) with full handle data
            $detail = $booking->bookingDetails()
                ->whereIn('detail_type', ['initial'])
                ->whereIn('status', ['draft', 'aktif'])
                ->latest()
                ->first();

            if (!$detail) {
                $detail = $booking->bookingDetails()->create([
                    'detail_type' => 'initial',
                    'status'      => 'draft',
                ]);
            }

            $detail->update([
                'unit_id'            => $data['unit_id'],
                'driver_id'          => $data['driver_id'] ?? null,
                'lama_sewa'          => $data['lama_sewa'],
                'paket_sewa'         => $data['paket_sewa'],
                'harga_mobil'        => $data['harga_mobil'],
                'diskon_mobil'       => $data['diskon_mobil'] ?? 0,
                'pricing_mode'       => $data['pricing_mode'],
                'pricing_package_id' => $data['pricing_package_id'] ?? null,
                'harga_all_in'       => $data['harga_all_in'] ?? null,
                'status'             => 'draft',
            ]);

            // 3. Sync costs: delete old costs and recreate
            $detail->costs()->delete();

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

            $booking = $booking->fresh(['customer.member', 'bookingDetails']);
            $booking->update([
                'due_date' => app(BookingBillingService::class)->calculateDueDate($booking),
            ]);

            return $booking->fresh();
        });
    }

    /**
     * Checkout a booking (waiting_list → rental_unit).
     * Updates unit status to "Out" and booking_detail status to "aktif".
     */
    public function checkout(Booking $booking, bool $skipInspection = false): Booking
    {
        if ($booking->status !== 'waiting_list') {
            throw new \InvalidArgumentException(
                "Checkout hanya diperbolehkan untuk booking dengan status waiting_list."
            );
        }

        $physicalCheckService = app(PhysicalCheckService::class);

        if (! $skipInspection) {
            $physicalCheckService->assertCompletedOrRequest($booking, 'departure');
        }

        return DB::transaction(function () use ($booking, $skipInspection, $physicalCheckService) {
            if ($skipInspection) {
                $physicalCheckService->skipForBooking($booking, 'departure');
            }

            $booking->update([
                'status' => 'rental_unit',
                'checked_out_by' => auth()->id(),
                'checked_out_at' => now(),
            ]);

            $activeDetail = $booking->bookingDetails()
                ->whereIn('status', ['draft', 'aktif'])
                ->latest()
                ->first();

            if ($activeDetail) {
                $activeDetail->update(['status' => 'aktif']);

                if ($activeDetail->unit_id) {
                    \App\Models\Unit::where('id', $activeDetail->unit_id)
                        ->update(['status' => 'Out']);
                }
            }

            return $booking->fresh();
        });
    }

    /**
     * Complete a booking (rental_unit → selesai).
     * Updates unit status back to "Aktif" and booking_detail status to "selesai".
     */
    public function complete(Booking $booking, bool $skipInspection = false, ?string $returnedAt = null): Booking
    {
        if ($booking->status !== 'rental_unit') {
            throw new \InvalidArgumentException(
                "Complete hanya diperbolehkan untuk booking dengan status rental_unit."
            );
        }

        $physicalCheckService = app(PhysicalCheckService::class);

        if (! $skipInspection) {
            $physicalCheckService->assertCompletedOrRequest($booking, 'return');
        }

        return DB::transaction(function () use ($booking, $skipInspection, $returnedAt, $physicalCheckService) {
            if ($skipInspection) {
                $physicalCheckService->skipForBooking($booking, 'return');
            }

            $booking->update([
                'status' => 'selesai',
                'returned_at' => $returnedAt ? Carbon::parse($returnedAt)->format('Y-m-d H:i:s') : now(),
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);

            $activeDetail = $booking->bookingDetails()
                ->where('status', 'aktif')
                ->latest()
                ->first();

            if ($activeDetail) {
                $activeDetail->update(['status' => 'selesai']);

                if ($activeDetail->unit_id) {
                    \App\Models\Unit::where('id', $activeDetail->unit_id)
                        ->update(['status' => 'Aktif']);
                }

                app(RentToRentService::class)->syncDetail($activeDetail->fresh(['booking', 'unit.rentalOwner']));
            }

            // Sync cached_sisa_tagihan agar booking langsung muncul di daftar piutang
            $booking->unsetRelation('bookingDetails')->unsetRelation('payments');
            app(BookingBillingService::class)->updateCachedSisaTagihan($booking->fresh());

            return $booking->fresh();
        });
    }

    public function requestRentalUnitReturn(Booking $booking, string $reason): Booking
    {
        if ($booking->status !== 'selesai') {
            throw new \InvalidArgumentException(
                'Request kembali ke rental unit hanya untuk booking selesai.'
            );
        }

        if ($booking->rental_unit_return_status === 'pending') {
            throw new \InvalidArgumentException(
                'Request kembali ke rental unit masih menunggu approval supervisor.'
            );
        }

        $booking->update([
            'rental_unit_return_status' => 'pending',
            'rental_unit_return_reason' => $reason,
            'rental_unit_return_requested_by' => auth()->id(),
            'rental_unit_return_requested_at' => now(),
            'rental_unit_return_approved_by' => null,
            'rental_unit_return_approved_at' => null,
            'rental_unit_return_rejected_by' => null,
            'rental_unit_return_rejected_at' => null,
            'rental_unit_return_rejection_note' => null,
        ]);

        return $booking->fresh();
    }

    public function approveRentalUnitReturn(Booking $booking): Booking
    {
        if ($booking->rental_unit_return_status !== 'pending') {
            throw new \InvalidArgumentException(
                'Hanya request kembali ke rental unit berstatus pending yang bisa disetujui.'
            );
        }

        if ($booking->rental_unit_return_requested_by === auth()->id() && auth()->user()?->role !== 'superadmin') {
            throw new \InvalidArgumentException(
                'Request harus di-ACC oleh supervisor lain.'
            );
        }

        return DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => 'rental_unit',
                'returned_at' => null,
                'completed_at' => null,
                'completed_by' => null,
                'rental_unit_return_status' => 'approved',
                'rental_unit_return_approved_by' => auth()->id(),
                'rental_unit_return_approved_at' => now(),
            ]);

            $detail = $booking->bookingDetails()
                ->where('status', 'selesai')
                ->latest()
                ->first();

            if ($detail) {
                $detail->update(['status' => 'aktif']);

                if ($detail->unit_id) {
                    \App\Models\Unit::where('id', $detail->unit_id)
                        ->update(['status' => 'Out']);
                }
            }

            return $booking->fresh();
        });
    }

    public function rejectRentalUnitReturn(Booking $booking, ?string $note = null): Booking
    {
        if ($booking->rental_unit_return_status !== 'pending') {
            throw new \InvalidArgumentException(
                'Hanya request kembali ke rental unit berstatus pending yang bisa ditolak.'
            );
        }

        $booking->update([
            'rental_unit_return_status' => 'rejected',
            'rental_unit_return_rejected_by' => auth()->id(),
            'rental_unit_return_rejected_at' => now(),
            'rental_unit_return_rejection_note' => $note,
        ]);

        return $booking->fresh();
    }

    /**
     * Transition a booking to a new status.
     * Allowed transitions are enforced here, not in the controller.
     */
    public function changeStatus(Booking $booking, array $data): Booking
    {
        $allowed = $this->getAllowedTransitions($booking->status);

        if (! in_array($data['status'], $allowed)) {
            throw new \InvalidArgumentException(
                "Cannot transition from [{$booking->status}] to [{$data['status']}]."
            );
        }

        $updates = [
            'status'         => $data['status'],
            'catatan_status' => $data['catatan_status'] ?? $booking->catatan_status,
        ];

        if ($data['status'] === 'confirm') {
            $updates['confirmed_by'] = auth()->id();
            $updates['confirmed_at'] = now();
        }

        if ($data['status'] === 'waiting_list') {
            $updates['handled_by'] = auth()->id();
            $updates['handled_at'] = now();
        }

        if ($data['status'] === 'rental_unit') {
            $updates['checked_out_by'] = auth()->id();
            $updates['checked_out_at'] = now();
        }

        if ($data['status'] === 'selesai') {
            $updates['returned_at'] = isset($data['returned_at'])
                ? Carbon::parse($data['returned_at'])->format('Y-m-d H:i:s')
                : now();
            $updates['completed_by'] = auth()->id();
            $updates['completed_at'] = now();
        }

        $booking->update($updates);

        if ($data['status'] === 'waiting_list') {
            $booking = $booking->fresh(['customer.member', 'bookingDetails']);
            $booking->update([
                'due_date' => app(BookingBillingService::class)->calculateDueDate($booking),
            ]);
        }

        if (in_array($data['status'], ['waiting_list', 'batal', 'selesai'], true)) {
            $booking->bookingDetails()
                ->with(['booking', 'unit.rentalOwner'])
                ->get()
                ->each(fn(BookingDetail $detail) => app(RentToRentService::class)->syncDetail($detail));
        }

        // Sync cache setelah selesai agar piutang langsung muncul
        if ($data['status'] === 'selesai') {
            $booking->unsetRelation('bookingDetails')->unsetRelation('payments');
            app(BookingBillingService::class)->updateCachedSisaTagihan($booking->fresh());
        }

        return $booking->fresh();
    }

    private function getAllowedTransitions(string $current): array
    {
        return match ($current) {
            'follow_up'    => ['confirm', 'waiting_list', 'batal'],
            'confirm'      => ['waiting_list', 'batal'],
            'waiting_list' => ['rental_unit', 'batal'],
            'rental_unit'  => ['selesai', 'batal'],
            default        => [],
        };
    }

    private function normalizeCustomerStatus(string $status): string
    {
        return strtolower($status) === 'corporate' ? 'Corporate' : 'Normal';
    }
}
