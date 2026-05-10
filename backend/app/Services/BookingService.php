<?php

namespace App\Services;

use App\Exceptions\BookingBlacklistException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Customer;
use App\Traits\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class BookingService
{
    use PhoneNormalizer;

    public function createBooking(array $data, int $branchId, int $tenantId): Booking
    {
        return DB::transaction(function () use ($data, $branchId, $tenantId) {
            $customerId = $data['customer_id'] ?? null;

            // 1. If new customer, create one
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
                        'kota' => $data['customer_city'] ?? '-',
                        'status' => 'Normal',
                    ]);
                    $customerId = $customer->id;
                }
            }

            // 2. Check if customer is blacklisted
            $customer = Customer::findOrFail($customerId);
            if ($customer->status === 'Blacklist') {
                throw new BookingBlacklistException("Pelanggan diblacklist. Booking tidak bisa dilanjutkan.");
            }

            // 3. Set status based on DP
            $hasDp = isset($data['dp']) && $data['dp'] > 0;
            $status = $hasDp ? 'confirm' : 'follow_up';

            // 4. Generate booking code: BK-YYYYMM-XXXXX
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

            // 5. Create Booking
            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'kode_booking' => $kodeBooking,
                'status' => $status,
                'harga_dealing' => $data['harga_dealing'] ?? null,
                'dp' => $data['dp'] ?? null,
                'rekening_dp_id' => $data['rekening_dp_id'] ?? null,
                'tujuan' => $data['tujuan'] ?? null,
                'alamat_penjemputan' => $data['alamat_penjemputan'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            // 6. Create Booking Detail
            BookingDetail::create([
                'booking_id' => $booking->id,
                'unit_id' => $data['unit_id'] ?? null,
                'unit_placeholder' => $data['unit_placeholder'] ?? null,
                'driver_id' => null, // Will be handled in later phases
                'tgl_sewa' => $data['tgl_sewa'],
                'tgl_kembali' => $data['tgl_kembali'],
                'harga_mobil' => null, // Can be refined later
                'diskon_mobil' => 0,
                'detail_type' => 'initial',
                'status' => 'draft',
            ]);

            return $booking->load(['customer', 'bookingDetails']);
        });
    }
}
