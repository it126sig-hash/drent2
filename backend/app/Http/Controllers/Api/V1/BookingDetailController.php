<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingDetailRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Http\Requests\UpdateBookingDetailRequest;
use App\Models\BookingDetail;

class BookingDetailController extends Controller
{
    use AuthorizesRequests;

    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function store(StoreBookingDetailRequest $request, Booking $booking)
    {
        $this->authorize('update', $booking);
        
        $this->bookingService->assignDetail($booking, $request->validated());
        
        return new BookingResource($booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs']));
    }

    public function update(UpdateBookingDetailRequest $request, BookingDetail $bookingDetail)
    {
        $this->authorize('update', $bookingDetail->booking);

        $bookingDetail->update([
            'unit_id' => $request->unit_id,
            'driver_id' => $request->driver_id,
            'tgl_sewa' => \Carbon\Carbon::parse($request->tgl_sewa)->format('Y-m-d H:i:s'),
            'tgl_kembali' => \Carbon\Carbon::parse($request->tgl_kembali)->format('Y-m-d H:i:s'),
            'harga_mobil' => $request->harga_mobil,
            'diskon_mobil' => $request->diskon_mobil ?? 0,
        ]);

        return new BookingResource($bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs']));
    }
}
