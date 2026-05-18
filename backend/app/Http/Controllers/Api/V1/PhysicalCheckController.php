<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSignPhysicalCheckRequest;
use App\Http\Requests\RequestPhysicalCheckRequest;
use App\Http\Requests\StorePhysicalCheckRequest;
use App\Http\Resources\PhysicalCheckBookingResource;
use App\Http\Resources\PhysicalCheckItemResource;
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

        $check = $this->service->storeCompleted($request->validated(), $request);

        return new PhysicalCheckResource($check);
    }

    public function publicShow(string $token)
    {
        $check = $this->service->findPublic($token);
        $booking = $check->booking->loadMissing(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.driver']);

        return response()->json([
            'data' => [
                'check' => new PhysicalCheckResource($check),
                'booking' => [
                    'id' => $booking->id,
                    'kode_booking' => $booking->kode_booking,
                    'status' => $booking->status,
                    'customer' => [
                        'id' => $booking->customer?->id,
                        'nama' => $booking->customer?->nama,
                        'email' => $booking->customer?->email,
                        'status' => $booking->customer?->status,
                    ],
                    'booking_details' => $booking->bookingDetails->map(fn($detail) => [
                        'id' => $detail->id,
                        'unit_placeholder' => $detail->unit_placeholder,
                        'tgl_sewa' => $detail->tgl_sewa,
                        'tgl_kembali' => $detail->tgl_kembali,
                        'detail_type' => $detail->detail_type,
                        'status' => $detail->status,
                        'unit' => $detail->unit ? [
                            'id' => $detail->unit->id,
                            'no_polisi' => $detail->unit->no_polisi,
                            'merk' => $detail->unit->merk,
                            'tipe' => $detail->unit->tipe,
                        ] : null,
                    ])->values(),
                ],
                'items' => PhysicalCheckItemResource::collection($this->service->publicItems($check))->resolve(),
            ],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function publicRequestOtp(string $token, Request $request)
    {
        $check = $this->service->findPublic($token);
        $this->service->requestOtp($check, $request);

        return response()->json([
            'data' => ['sent' => true],
            'message' => 'Kode OTP dikirim ke email penyewa.',
            'errors' => null,
        ]);
    }

    public function publicActivity(string $token, Request $request)
    {
        $request->validate([
            'event' => ['required', 'string', 'max:80'],
            'context' => ['nullable', 'array'],
        ]);

        $check = $this->service->findPublic($token);
        $this->service->recordActivity($check, $request->input('event'), $request->input('context', []), $request, 'customer');

        return response()->json([
            'data' => ['recorded' => true],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function publicStore(string $token, PublicSignPhysicalCheckRequest $request): PhysicalCheckResource
    {
        $check = $this->service->findPublic($token);
        $stored = $this->service->storePublicSignature($check, $request->validated(), $request);

        return new PhysicalCheckResource($stored);
    }
}
