<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\CheckoutBookingRequest;
use App\Http\Requests\CompleteBookingRequest;
use App\Http\Requests\HandleBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BookingCollection;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BookingController extends Controller
{
    use AuthorizesRequests;

    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): BookingCollection
    {
        $this->authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->with(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'payments'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->date_from, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to,   fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return new BookingCollection($bookings);
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(StoreBookingRequest $request)
    {
        $this->authorize('create', Booking::class);

        $user = auth()->user();
        
        $booking = $this->bookingService->createBooking(
            $request->validated(),
            $user->branch_id,
            $user->tenant_id
        );

        return new BookingResource($booking);
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking): BookingResource
    {
        $this->authorize('view', $booking);
        $booking->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']);
        return new BookingResource($booking);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);

        $booking->update($request->validated());

        return new BookingResource($booking->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    /**
     * Update the status of the specified resource.
     */
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('updateStatus', $booking);
        $updated = $this->bookingService->changeStatus($booking, $request->validated());
        return new BookingResource($updated->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    /**
     * Handle a booking: assign unit/driver/costs and move to waiting_list (C4).
     */
    public function handle(HandleBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);
        $handled = $this->bookingService->handleBooking($booking, $request->validated());
        return new BookingResource($handled->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    /**
     * Checkout a booking (waiting_list → rental_unit).
     * C5: unit status → Out, booking_detail status → aktif.
     */
    public function checkout(CheckoutBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('checkout', $booking);
        $skipInspection = $request->boolean('skip_inspection', false);
        $updated = $this->bookingService->checkout($booking, $skipInspection);
        return new BookingResource($updated->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    /**
     * Complete a booking (rental_unit → selesai).
     * C6: unit status → Aktif, booking_detail status → selesai.
     */
    public function complete(CompleteBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('complete', $booking);
        $skipInspection = $request->boolean('skip_inspection', false);
        $updated = $this->bookingService->complete($booking, $skipInspection);
        return new BookingResource($updated->load(['customer', 'createdBy', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }
}
