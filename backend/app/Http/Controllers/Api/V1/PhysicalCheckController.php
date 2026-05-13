<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestPhysicalCheckRequest;
use App\Http\Requests\StorePhysicalCheckRequest;
use App\Http\Resources\PhysicalCheckBookingResource;
use App\Http\Resources\PhysicalCheckResource;
use App\Models\Booking;
use App\Models\PhysicalCheck;
use App\Services\PhysicalCheckService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PhysicalCheckController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PhysicalCheckService $service)
    {
    }

    public function bookings(Request $request)
    {
        $this->authorize('viewAny', PhysicalCheck::class);

        $bookings = $this->service->listOperationalBookings($request->only([
            'branch_id',
            'search',
            'per_page',
        ]));

        return PhysicalCheckBookingResource::collection($bookings);
    }

    public function show(PhysicalCheck $physicalCheck): PhysicalCheckResource
    {
        $this->authorize('view', $physicalCheck);

        return new PhysicalCheckResource($physicalCheck->load(PhysicalCheckService::RELATIONS));
    }

    public function showByBooking(Booking $booking, string $type): PhysicalCheckResource
    {
        $this->authorize('view', $booking);

        abort_unless(in_array($type, ['departure', 'return'], true), 404);

        $check = $this->service->findForBooking($booking, $type);

        if (! $check) {
            abort(404, 'Cek fisik belum tersedia.');
        }

        $this->authorize('view', $check);

        return new PhysicalCheckResource($check);
    }

    public function request(RequestPhysicalCheckRequest $request): PhysicalCheckResource
    {
        $this->authorize('create', PhysicalCheck::class);

        $data = $request->validated();
        $booking = Booking::findOrFail($data['booking_id']);
        $this->authorize('view', $booking);

        $check = $this->service->requestForBooking($booking, $data['type']);

        return new PhysicalCheckResource($check);
    }

    public function store(StorePhysicalCheckRequest $request): PhysicalCheckResource
    {
        $this->authorize('create', PhysicalCheck::class);

        $booking = Booking::findOrFail($request->validated('booking_id'));
        $this->authorize('view', $booking);

        $check = $this->service->storeCompleted($request->validated());

        return new PhysicalCheckResource($check);
    }
}
