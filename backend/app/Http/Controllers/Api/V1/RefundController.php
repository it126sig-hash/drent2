<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Booking;
use App\Services\RefundService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RefundController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(RefundService $service)
    {
        $this->service = $service;
    }

    /**
     * List refunds for a booking.
     * GET /api/v1/bookings/{booking}/refunds
     */
    public function index(Booking $booking)
    {
        $this->authorize('view', $booking);

        $refunds = $this->service->listForBooking($booking);

        return RefundResource::collection($refunds);
    }

    /**
     * Store a new refund for a booking.
     * POST /api/v1/bookings/{booking}/refund
     */
    public function store(StoreRefundRequest $request, Booking $booking)
    {
        $this->authorize('manageRefunds', $booking);

        $refund = $this->service->create($booking, $request->validated());
        $refund->load(['paymentAccount', 'creator']);

        return new RefundResource($refund);
    }
}
