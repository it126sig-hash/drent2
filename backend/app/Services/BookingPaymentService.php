<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BookingPaymentService
{
    /**
     * List payments for a booking.
     */
    public function listForBooking(Booking $booking)
    {
        return $booking->payments()
            ->with(['paymentAccount', 'creator', 'reallocatedFrom'])
            ->latest()
            ->get();
    }

    /**
     * Create a new payment for a booking.
     */
    public function create(Booking $booking, array $data): BookingPayment
    {
        $data['booking_id'] = $booking->id;
        $data['created_by'] = Auth::id();
        $data['paid_at'] = $data['paid_at'] ?? now();

        return BookingPayment::create($data);
    }

    /**
     * Reallocate a payment to another booking.
     *
     * Creates a new payment on the target booking referencing the original,
     * and the original amount is logically "moved".
     */
    public function reallocate(BookingPayment $payment, array $data): BookingPayment
    {
        $targetBooking = Booking::findOrFail($data['target_booking_id']);

        // Validate: cannot reallocate to the same booking
        if ($targetBooking->id === $payment->booking_id) {
            throw ValidationException::withMessages([
                'target_booking_id' => ['Tidak bisa realokasi ke booking yang sama.'],
            ]);
        }

        // Validate: reallocation amount cannot exceed original payment amount
        $alreadyReallocated = BookingPayment::where('reallocated_from_id', $payment->id)
            ->sum('amount');
        $remaining = $payment->amount - $alreadyReallocated;

        if ($data['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => ["Jumlah realokasi melebihi sisa pembayaran yang tersedia (Rp {$remaining})."],
            ]);
        }

        return BookingPayment::create([
            'booking_id'          => $targetBooking->id,
            'payment_account_id'  => $data['payment_account_id'],
            'amount'              => $data['amount'],
            'payment_type'        => $data['payment_type'],
            'catatan'             => $data['catatan'] ?? "Realokasi dari booking #{$payment->booking_id}",
            'paid_at'             => now(),
            'reallocated_from_id' => $payment->id,
            'created_by'          => Auth::id(),
        ]);
    }
}
