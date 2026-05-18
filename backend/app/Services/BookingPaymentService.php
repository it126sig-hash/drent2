<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingBillingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BookingPaymentService
{
    public function __construct(private BookingBillingService $billingService)
    {
    }
    /**
     * List payments for a booking.
     */
    public function listForBooking(Booking $booking)
    {
        return $booking->payments()
            ->with(['paymentAccount', 'creator', 'reallocatedFrom', 'voidRequester', 'voidApprover', 'voidRejecter'])
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

        $payment = BookingPayment::create($data);

        if (($data['payment_type'] ?? null) === 'dp') {
            $booking->update([
                'status' => $booking->status === 'follow_up' ? 'confirm' : $booking->status,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);
        }

        // Sync kolom cached_sisa_tagihan setelah pembayaran dicatat
        $booking->load('payments'); // pastikan relasi segar
        $this->billingService->updateCachedSisaTagihan($booking);

        return $payment;
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

        $newPayment = BookingPayment::create([
            'booking_id'          => $targetBooking->id,
            'payment_account_id'  => $data['payment_account_id'],
            'amount'              => $data['amount'],
            'payment_type'        => $data['payment_type'],
            'catatan'             => $data['catatan'] ?? "Realokasi dari booking #{$payment->booking_id}",
            'paid_at'             => now(),
            'reallocated_from_id' => $payment->id,
            'created_by'          => Auth::id(),
        ]);

        // Sync cache untuk kedua booking yang terdampak
        $sourceBooking = $payment->booking;
        $sourceBooking->load('payments');
        $this->billingService->updateCachedSisaTagihan($sourceBooking);
        $targetBooking->load('payments');
        $this->billingService->updateCachedSisaTagihan($targetBooking);

        return $newPayment;
    }

    public function requestVoid(BookingPayment $payment, string $reason): BookingPayment
    {
        if ($payment->status === 'voided') {
            throw ValidationException::withMessages([
                'payment' => ['Pembayaran ini sudah void.'],
            ]);
        }

        if ($payment->status === 'void_requested') {
            throw ValidationException::withMessages([
                'payment' => ['Request void pembayaran ini masih menunggu approval supervisor.'],
            ]);
        }

        $payment->update([
            'status' => 'void_requested',
            'void_reason' => $reason,
            'void_requested_by' => Auth::id(),
            'void_requested_at' => now(),
            'void_approved_by' => null,
            'void_approved_at' => null,
            'void_rejected_by' => null,
            'void_rejected_at' => null,
            'void_rejection_note' => null,
        ]);

        return $payment->fresh(['paymentAccount', 'creator', 'reallocatedFrom', 'voidRequester', 'voidApprover', 'voidRejecter']);
    }

    public function approveVoid(BookingPayment $payment): BookingPayment
    {
        if ($payment->status !== 'void_requested') {
            throw ValidationException::withMessages([
                'payment' => ['Hanya pembayaran dengan status request void yang bisa di-approve.'],
            ]);
        }

        if ($payment->void_requested_by === Auth::id() && Auth::user()?->role !== 'superadmin') {
            throw ValidationException::withMessages([
                'payment' => ['Request void harus di-ACC oleh supervisor lain.'],
            ]);
        }

        $payment->update([
            'status' => 'voided',
            'void_approved_by' => Auth::id(),
            'void_approved_at' => now(),
        ]);

        // Sync cache — void mengembalikan sisa tagihan booking
        $booking = $payment->booking()->with(['bookingDetails.costs', 'payments'])->first();
        if ($booking) {
            $this->billingService->updateCachedSisaTagihan($booking);
        }

        return $payment->fresh(['paymentAccount', 'creator', 'reallocatedFrom', 'voidRequester', 'voidApprover', 'voidRejecter']);
    }

    public function rejectVoid(BookingPayment $payment, ?string $note = null): BookingPayment
    {
        if ($payment->status !== 'void_requested') {
            throw ValidationException::withMessages([
                'payment' => ['Hanya pembayaran dengan status request void yang bisa ditolak.'],
            ]);
        }

        $payment->update([
            'status' => 'active',
            'void_rejected_by' => Auth::id(),
            'void_rejected_at' => now(),
            'void_rejection_note' => $note,
        ]);

        return $payment->fresh(['paymentAccount', 'creator', 'reallocatedFrom', 'voidRequester', 'voidApprover', 'voidRejecter']);
    }
}
