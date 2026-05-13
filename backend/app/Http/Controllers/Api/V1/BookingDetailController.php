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
        
        return new BookingResource($booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }

    public function update(UpdateBookingDetailRequest $request, BookingDetail $bookingDetail)
    {
        $this->authorize('update', $bookingDetail->booking);

        if ($bookingDetail->detail_type === 'extend' && in_array($bookingDetail->status, ['selesai', 'batal'], true)) {
            abort(422, 'Transaksi extend yang sudah selesai atau batal tidak bisa diedit.');
        }

        $bookingDetail->update([
            'unit_id' => $request->unit_id,
            'unit_placeholder' => null,
            'driver_id' => $request->driver_id,
            'tgl_sewa' => \Carbon\Carbon::parse($request->tgl_sewa)->format('Y-m-d H:i:s'),
            'tgl_kembali' => \Carbon\Carbon::parse($request->tgl_kembali)->format('Y-m-d H:i:s'),
            'harga_mobil' => $request->harga_mobil,
            'diskon_mobil' => $request->diskon_mobil ?? 0,
            'lama_sewa' => $request->lama_sewa,
            'paket_sewa' => $request->paket_sewa,
            'pricing_mode' => $request->pricing_mode,
            'pricing_package_id' => $request->pricing_package_id,
            'harga_all_in' => $request->harga_all_in,
        ]);

        $bookingDetail->costs()->delete();
        foreach ($request->input('costs', []) as $costData) {
            $bookingDetail->costs()->create([
                'cost_type_id' => $costData['cost_type_id'] ?? null,
                'type' => $costData['type'] ?? 'biaya',
                'label' => $costData['label'],
                'amount' => $costData['amount'],
                'keterangan' => $costData['keterangan'] ?? null,
            ]);
        }

        return new BookingResource($bookingDetail->booking->load(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver', 'bookingDetails.costs.costType', 'payments', 'refunds']));
    }
}
