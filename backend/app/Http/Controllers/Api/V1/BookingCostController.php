<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingCostRequest;
use App\Http\Requests\StoreAdditionalCostRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Services\BookingBillingService;
use App\Services\BookingService;
use App\Services\BookingModificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Http\Requests\UpdateBookingCostRequest;
use App\Models\BookingCost;

class BookingCostController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BookingService $bookingService,
        protected BookingModificationService $modificationService,
        protected BookingBillingService $billingService,
    ) {}

    public function store(StoreBookingCostRequest $request, BookingDetail $bookingDetail)
    {
        $this->authorize('update', $bookingDetail->booking);
        
        $this->bookingService->addCost($bookingDetail, $request->validated());

        // Biaya berubah — sync cache
        $booking = $bookingDetail->booking->load(['bookingDetails.costs', 'payments']);
        $this->billingService->updateCachedSisaTagihan($booking);

        return new BookingResource($bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType']));
    }

    public function update(UpdateBookingCostRequest $request, BookingCost $bookingCost)
    {
        $this->authorize('update', $bookingCost->bookingDetail->booking);

        $bookingCost->update($request->validated());

        // Biaya diupdate — sync cache
        $booking = $bookingCost->bookingDetail->booking->load(['bookingDetails.costs', 'payments']);
        $this->billingService->updateCachedSisaTagihan($booking);

        return new BookingResource($bookingCost->bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType']));
    }

    public function storeAdditionalCost(StoreAdditionalCostRequest $request, Booking $booking)
    {
        $this->authorize('update', $booking);
        
        $this->modificationService->addAdditionalCost($booking, $request->validated());

        // Biaya tambahan berubah — sync cache
        $booking->load(['bookingDetails.costs', 'payments']);
        $this->billingService->updateCachedSisaTagihan($booking);

        return new BookingResource($booking->fresh()->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }
}
