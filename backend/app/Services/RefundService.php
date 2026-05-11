<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Refund;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Create a refund for a booking.
     */
    public function create(Booking $booking, array $data): Refund
    {
        $data['booking_id'] = $booking->id;
        $data['created_by'] = Auth::id();
        $data['refunded_at'] = $data['refunded_at'] ?? now();

        return Refund::create($data);
    }
}
