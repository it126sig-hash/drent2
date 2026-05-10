<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
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
}
