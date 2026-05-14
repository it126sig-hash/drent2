<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingPaymentRequest;
use App\Http\Requests\ReallocatePaymentRequest;
use App\Http\Requests\RejectVoidBookingPaymentRequest;
use App\Http\Requests\RequestVoidBookingPaymentRequest;
use App\Http\Resources\BookingPaymentResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingPaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BookingPaymentController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(BookingPaymentService $service)
    {
        $this->service = $service;
    }

    /**
     * List payments for a booking.
     * GET /api/v1/bookings/{booking}/payments
     */
    public function index(Booking $booking)
    {
        $this->authorize('view', $booking);

        $payments = $this->service->listForBooking($booking);

        return BookingPaymentResource::collection($payments);
    }

    /**
     * Store a new payment for a booking.
     * POST /api/v1/bookings/{booking}/payments
     */
    public function store(StoreBookingPaymentRequest $request, Booking $booking)
    {
        $this->authorize('managePayments', $booking);

        $payment = $this->service->create($booking, $request->validated());
        $payment->load(['paymentAccount', 'creator']);

        return new BookingPaymentResource($payment);
    }

    /**
     * Reallocate a payment to another booking.
     * POST /api/v1/booking-payments/{bookingPayment}/reallocate
     */
    public function reallocate(ReallocatePaymentRequest $request, BookingPayment $bookingPayment)
    {
        $this->authorize('managePayments', $bookingPayment->booking);

        $newPayment = $this->service->reallocate($bookingPayment, $request->validated());
        $newPayment->load(['paymentAccount', 'creator', 'reallocatedFrom']);

        return new BookingPaymentResource($newPayment);
    }

    public function requestVoid(RequestVoidBookingPaymentRequest $request, BookingPayment $bookingPayment)
    {
        $this->authorize('managePayments', $bookingPayment->booking);

        $payment = $this->service->requestVoid($bookingPayment, $request->validated()['void_reason']);

        return new BookingPaymentResource($payment);
    }

    public function approveVoid(BookingPayment $bookingPayment)
    {
        $this->authorize('approvePaymentVoid', $bookingPayment->booking);

        $payment = $this->service->approveVoid($bookingPayment);

        return new BookingPaymentResource($payment);
    }

    public function rejectVoid(RejectVoidBookingPaymentRequest $request, BookingPayment $bookingPayment)
    {
        $this->authorize('approvePaymentVoid', $bookingPayment->booking);

        $payment = $this->service->rejectVoid(
            $bookingPayment,
            $request->validated()['void_rejection_note'] ?? null
        );

        return new BookingPaymentResource($payment);
    }
}
