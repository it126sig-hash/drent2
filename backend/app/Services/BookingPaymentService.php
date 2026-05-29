<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentAccount;
use App\Services\BookingBillingService;
use App\Services\PaymentAccountTransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPaymentService
{
    public function __construct(
        private BookingBillingService $billingService,
        private PaymentAccountTransactionService $transactionService
    ) {
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
        return DB::transaction(function () use ($booking, $data) {
            $data['booking_id'] = $booking->id;
            $data['created_by'] = Auth::id();
            $data['paid_at'] = \App\Helpers\DateHelper::parseDateWithCurrentTime($data['paid_at'] ?? null);

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

            // Update saldo rekening (uang masuk)
            $account = PaymentAccount::lockForUpdate()->findOrFail($payment->payment_account_id);
            $this->transactionService->applyDelta($account, (int) $payment->amount, [
                'type' => 'booking_payment_in',
                'amount' => (int) $payment->amount,
                'description' => "Pembayaran booking #{$booking->kode_booking} ({$payment->payment_type})",
                'created_by' => Auth::id(),
                'transaction_at' => $payment->paid_at ?? now(),
            ]);

            return $payment;
        });
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
        return DB::transaction(function () use ($payment) {
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

            // Kurangi saldo rekening (void = mengembalikan uang)
            $account = PaymentAccount::lockForUpdate()->findOrFail($payment->payment_account_id);
            $this->transactionService->applyDelta($account, -(int) $payment->amount, [
                'type' => 'booking_payment_void',
                'amount' => (int) $payment->amount,
                'description' => "Void pembayaran booking #{$booking->kode_booking} ({$payment->payment_type}): " . $payment->void_reason,
                'created_by' => Auth::id(),
                'transaction_at' => now(),
            ]);

            return $payment->fresh(['paymentAccount', 'creator', 'reallocatedFrom', 'voidRequester', 'voidApprover', 'voidRejecter']);
        });
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
