<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class BookingBillingService
{
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

            $rental = ((int) $detail->harga_mobil - (int) $detail->diskon_mobil) * $duration;
            $costs = $detail->relationLoaded('costs')
                ? $detail->costs->sum(fn($cost) => $cost->type === 'diskon' ? -((int) $cost->amount) : (int) $cost->amount)
                : 0;

            return $rental + $costs;
        });
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
