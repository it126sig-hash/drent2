<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingDetail;
use Carbon\Carbon;

class BookingBillingService
{
    /**
     * Subtotal sewa mobil untuk satu detail (mode non all-in).
     * `harga_mobil` per periode, `diskon_mobil` adalah total diskon baris ini.
     */
    public function detailRentalSubtotal(BookingDetail $detail): int
    {
        $duration = max(1, (int) ($detail->lama_sewa ?? 1));

        return max(0, ((int) $detail->harga_mobil * $duration) - (int) $detail->diskon_mobil);
    }

    public function totalTagihan(Booking $booking): int
    {
        if (! $booking->relationLoaded('bookingDetails')) {
            $booking->load('bookingDetails.costs');
        }

        $details = $booking->bookingDetails->whereNotIn('status', ['batal']);

        return (int) $details->sum(function ($detail) {
            $duration = $detail->lama_sewa ?? 1;

            if ($detail->pricing_mode === 'all_in') {
                $additional = $detail->relationLoaded('costs')
                    ? $detail->costs->sum(fn($cost) =>
                        $cost->type === 'diskon'
                            ? -((int) $cost->amount)
                            : ((bool) $cost->is_additional ? (int) $cost->amount : 0)
                    )
                    : 0;

                return ((int) ($detail->harga_all_in ?? 0) * $duration) + $additional;
            }

            $rental = $this->detailRentalSubtotal($detail);
            $costs = $detail->relationLoaded('costs')
                ? $detail->costs->sum(fn($cost) => $cost->type === 'diskon' ? -((int) $cost->amount) : (int) $cost->amount)
                : 0;

            return $rental + $costs;
        });
    }

    public function usesDealingAmount(Booking $booking): bool
    {
        return in_array($booking->status, ['follow_up', 'confirm'], true)
            && (int) $booking->harga_dealing > 0;
    }

    public function invoiceAmount(Booking $booking): int
    {
        return $this->usesDealingAmount($booking)
            ? (int) $booking->harga_dealing
            : $this->totalTagihan($booking);
    }

    public function paidAmount(Booking $booking): int
    {
        if (! $booking->relationLoaded('payments')) {
            $booking->load('payments');
        }

        return (int) $booking->payments
            ->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided')
            ->sum('amount');
    }

    public function sisaTagihan(Booking $booking): int
    {
        return max(0, $this->totalTagihan($booking) - $this->paidAmount($booking));
    }

    /**
     * Hitung ulang sisa tagihan dan simpan ke kolom cached_sisa_tagihan.
     * Dipanggil setiap kali ada perubahan pada biaya atau pembayaran booking.
     */
    public function updateCachedSisaTagihan(Booking $booking): void
    {
        if (! $booking->relationLoaded('bookingDetails')) {
            $booking->load('bookingDetails.costs');
        }
        if (! $booking->relationLoaded('payments')) {
            $booking->load('payments');
        }

        $sisa = $this->sisaTagihan($booking);

        // updateQuietly agar tidak trigger observer/event & tidak update updated_at
        $booking->updateQuietly(['cached_sisa_tagihan' => $sisa]);
    }

    /**
     * Total nominal yang sudah "diklaim" oleh invoice-invoice aktif (non-void) milik booking ini,
     * terlepas dari sudah dibayar atau belum — sekali sebuah invoice mengklaim suatu nominal,
     * nominal itu tidak boleh diklaim lagi oleh invoice baru.
     */
    public function invoicedClaimAmount(Booking $booking): int
    {
        return (int) $booking->invoices()
            ->where('invoices.status', '!=', 'void')
            ->sum('invoice_bookings.amount');
    }

    /**
     * Pembayaran yang masuk langsung ke booking ini di luar invoice manapun (misal DP yang
     * dibayar sebelum invoice dibuat). Ikut mengurangi sisa yang boleh diklaim invoice baru.
     */
    public function directPaymentsAmount(Booking $booking): int
    {
        if (! $booking->relationLoaded('payments')) {
            $booking->load('payments');
        }

        return (int) $booking->payments
            ->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided' && $payment->invoice_payment_id === null)
            ->sum('amount');
    }

    /**
     * Sisa nominal booking ini yang masih boleh dimasukkan ke invoice baru (belum diklaim
     * invoice manapun, dan belum dibayar langsung di luar invoice). Dipakai sebagai batas
     * validasi saat generate invoice & hint di UI. Memakai invoiceAmount() (bukan totalTagihan()
     * mentah) supaya booking yang masih follow_up/confirm dengan harga_dealing tetap bisa
     * di-invoice sebelum unit & biaya item-based-nya final.
     */
    public function invoiceableBalance(Booking $booking): int
    {
        return max(0, $this->invoiceAmount($booking)
            - $this->invoicedClaimAmount($booking)
            - $this->directPaymentsAmount($booking));
    }

    public function calculateDueDate(Booking $booking): ?Carbon
    {
        if (! $booking->relationLoaded('customer')) {
            $booking->load('customer.member');
        }

        if (! $booking->relationLoaded('bookingDetails')) {
            $booking->load('bookingDetails');
        }

        $returnDate = $booking->bookingDetails
            ->pluck('tgl_kembali')
            ->filter()
            ->map(fn($date) => Carbon::parse($date))
            ->sortDesc()
            ->first();

        if (! $returnDate) {
            return null;
        }

        $customerStatus = strtolower((string) $booking->customer?->status);
        $isMember = $booking->customer?->member
            && strtolower((string) $booking->customer->member->status_member) === 'aktif';

        if ($customerStatus === 'rent to rent') {
            return $returnDate->copy()->addDays(7);
        }

        if ($customerStatus === 'corporate') {
            return $returnDate->copy()->addDays(30);
        }

        if ($customerStatus === 'normal' || $isMember) {
            return $returnDate;
        }

        return $returnDate;
    }
}
