<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\ExtendBookingRequest;
use App\Http\Requests\RollingBookingRequest;
use App\Http\Requests\StopEarlyBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingModificationService;
use Illuminate\Http\Request;

class BookingModificationController extends Controller
{
    protected $service;

    public function __construct(BookingModificationService $service)
    {
        $this->service = $service;
    }

    public function extend(ExtendBookingRequest $request, Booking $booking)
    {
        $this->service->extend($booking, $request->validated());

        return new BookingResource($booking->fresh()->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    public function rolling(RollingBookingRequest $request, Booking $booking)
    {
        $this->service->rolling($booking, $request->validated());

        return new BookingResource($booking->fresh()->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    public function stopEarly(StopEarlyBookingRequest $request, Booking $booking)
    {
        $this->service->stopEarly($booking, $request->validated());

        return new BookingResource($booking->fresh()->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    /**
     * Batal: buat Refund opsional, ubah status batal, unit kembali Aktif (C7).
     */
    public function cancel(CancelBookingRequest $request, Booking $booking)
    {
        $updated = $this->service->cancel($booking, $request->validated());

        return new BookingResource($updated->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }
}
