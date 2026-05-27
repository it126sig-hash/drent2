<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentAccount;

class RefundService
{
    /**
     * List refunds for a booking.
     */
    public function listForBooking(Booking $booking)
    {
        return $booking->refunds()
            ->with(['paymentAccount', 'creator'])
            ->latest()
            ->get();
    }

    public function __construct(private PaymentAccountTransactionService $transactionService)
    {
    }

    /**
     * Create a refund for a booking.
     */
    public function create(Booking $booking, array $data): Refund
    {
        return DB::transaction(function () use ($booking, $data) {
            $data['booking_id'] = $booking->id;
            $data['created_by'] = Auth::id();
            $data['refunded_at'] = \App\Helpers\DateHelper::parseDateWithCurrentTime($data['refunded_at'] ?? null);

            $refund = Refund::create($data);

            if (!empty($data['payment_account_id'])) {
                $account = PaymentAccount::lockForUpdate()->findOrFail($data['payment_account_id']);
                $this->transactionService->applyDelta($account, -(int) $refund->amount, [
                    'type' => 'refund_out',
                    'amount' => (int) $refund->amount,
                    'description' => 'Refund untuk booking #' . $booking->kode_booking . ($refund->keterangan ? ' - ' . $refund->keterangan : ''),
                    'created_by' => Auth::id(),
                    'transaction_at' => $refund->refunded_at,
                ]);
            }

            return $refund;
        });
    }
}
