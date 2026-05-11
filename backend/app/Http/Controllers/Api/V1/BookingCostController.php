<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingCostRequest;
use App\Http\Requests\StoreAdditionalCostRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Services\BookingService;
use App\Services\BookingModificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Http\Requests\UpdateBookingCostRequest;
use App\Models\BookingCost;

class BookingCostController extends Controller
{
    use AuthorizesRequests;

    protected $bookingService;
    protected $modificationService;

    public function __construct(BookingService $bookingService, BookingModificationService $modificationService)
    {
        $this->bookingService = $bookingService;
        $this->modificationService = $modificationService;
    }

    public function store(StoreBookingCostRequest $request, BookingDetail $bookingDetail)
    {
        $this->authorize('update', $bookingDetail->booking);
        
        $this->bookingService->addCost($bookingDetail, $request->validated());
        
        return new BookingResource($bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs']));
    }

    public function update(UpdateBookingCostRequest $request, BookingCost $bookingCost)
    {
        $this->authorize('update', $bookingCost->bookingDetail->booking);

        $bookingCost->update($request->validated());

        return new BookingResource($bookingCost->bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs']));
    }

    public function storeAdditionalCost(StoreAdditionalCostRequest $request, Booking $booking)
    {
        $this->authorize('update', $booking);
        
        $this->modificationService->addAdditionalCost($booking, $request->validated());
        
        return new BookingResource($booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs']));
    }
}
