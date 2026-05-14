<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\CheckoutBookingRequest;
use App\Http\Requests\CompleteBookingRequest;
use App\Http\Requests\HandleBookingRequest;
use App\Http\Requests\RejectRentalUnitReturnRequest;
use App\Http\Requests\RequestRentalUnitReturnRequest;
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
    private array $bookingRelations = [
        'customer',
        'createdBy',
        'confirmedBy',
        'handledBy',
        'checkedOutBy',
        'completedBy',
        'rentalUnitReturnRequester',
        'rentalUnitReturnApprover',
        'rentalUnitReturnRejecter',
        'bookingDetails.unit.rentalOwner',
        'bookingDetails.driver',
        'bookingDetails.costs.costType',
        'payments.creator',
        'payments.voidRequester',
        'payments.voidApprover',
        'payments.voidRejecter',
        'refunds',
        'physicalChecks',
    ];

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

        $statuses = $this->arrayFilter($request->input('status'));
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $bookings = Booking::query()
            ->with(collect($this->bookingRelations)->reject(fn($relation) => $relation === 'refunds')->all())
            ->withMin('bookingDetails as first_rental_date', 'tgl_sewa')
            ->when($statuses, fn($q, $v) => $q->whereIn('status', $v))
            ->when($request->date_from, fn($q, $v) => $q->whereHas('bookingDetails', fn($detail) => $detail->whereDate('tgl_sewa', '>=', $v)))
            ->when($request->date_to,   fn($q, $v) => $q->whereHas('bookingDetails', fn($detail) => $detail->whereDate('tgl_sewa', '<=', $v)))
            ->when($request->customer_id, fn($q, $v) => $q->where('customer_id', $v))
            ->when($request->rental_owner_id, fn($q, $v) => $q->whereHas('bookingDetails.unit', fn($unit) => $unit->where('rental_owner_id', $v)))
            ->when($request->kota, fn($q, $v) => $q->where('kota', $v))
            ->when($request->search, function ($q, $v) {
                $q->where(function ($query) use ($v) {
                    $query->where('kode_booking', 'like', "%{$v}%")
                        ->orWhere('tujuan', 'like', "%{$v}%")
                        ->orWhere('kota', 'like', "%{$v}%")
                        ->orWhereHas('customer', fn($customer) => $customer->where('nama', 'like', "%{$v}%"));
                });
            });

        match ($sortBy) {
            'kode_booking' => $bookings->orderBy('kode_booking', $sortDirection),
            'tgl_sewa' => $bookings->orderBy('first_rental_date', $sortDirection)->orderBy('created_at', 'desc'),
            default => $bookings->orderBy('created_at', $sortDirection),
        };

        $bookings = $bookings->paginate($request->integer('per_page', 15));

        return new BookingCollection($bookings);
    }

    private function arrayFilter(mixed $value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter((array) $value, fn($item) => $item !== null && $item !== ''));
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
        $booking->load($this->bookingRelations);
        return new BookingResource($booking);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);

        $data = $request->validated();

        $booking->update(collect($data)->only([
            'lama_sewa',
            'paket_sewa',
            'harga_dealing',
            'dp',
            'rekening_dp_id',
            'tujuan',
            'kota',
            'alamat_penjemputan',
            'catatan',
        ])->all());

        if ($request->hasAny(['unit_id', 'unit_placeholder', 'tgl_sewa', 'tgl_kembali'])) {
            $detail = $booking->bookingDetails()
                ->where('detail_type', 'initial')
                ->latest()
                ->first() ?? $booking->bookingDetails()->latest()->first();

            if ($detail) {
                $detail->update([
                    'unit_id'          => $data['unit_id'] ?? null,
                    'unit_placeholder' => empty($data['unit_id']) ? ($data['unit_placeholder'] ?? null) : null,
                    'tgl_sewa'         => isset($data['tgl_sewa']) ? \Carbon\Carbon::parse($data['tgl_sewa'])->format('Y-m-d H:i:s') : $detail->tgl_sewa,
                    'tgl_kembali'      => isset($data['tgl_kembali']) ? \Carbon\Carbon::parse($data['tgl_kembali'])->format('Y-m-d H:i:s') : $detail->tgl_kembali,
                    'lama_sewa'        => $data['lama_sewa'] ?? $detail->lama_sewa,
                    'paket_sewa'       => $data['paket_sewa'] ?? $detail->paket_sewa,
                ]);
            }
        }

        return new BookingResource($booking->load($this->bookingRelations));
    }

    /**
     * Update the status of the specified resource.
     */
    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('updateStatus', $booking);
        $updated = $this->bookingService->changeStatus($booking, $request->validated());
        return new BookingResource($updated->load($this->bookingRelations));
    }

    /**
     * Handle a booking: assign unit/driver/costs and move to waiting_list (C4).
     */
    public function handle(HandleBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);
        $handled = $this->bookingService->handleBooking($booking, $request->validated());
        return new BookingResource($handled->load($this->bookingRelations));
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
        return new BookingResource($updated->load($this->bookingRelations));
    }

    /**
     * Complete a booking (rental_unit → selesai).
     * C6: unit status → Aktif, booking_detail status → selesai.
     */
    public function complete(CompleteBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('complete', $booking);
        $skipInspection = $request->boolean('skip_inspection', false);
        $validated = $request->validated();
        $updated = $this->bookingService->complete($booking, $skipInspection, $validated['returned_at'] ?? null);
        return new BookingResource($updated->load($this->bookingRelations));
    }

    public function requestRentalUnitReturn(RequestRentalUnitReturnRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('requestRentalUnitReturn', $booking);
        $updated = $this->bookingService->requestRentalUnitReturn($booking, $request->validated()['reason']);
        return new BookingResource($updated->load($this->bookingRelations));
    }

    public function approveRentalUnitReturn(Booking $booking): BookingResource
    {
        $this->authorize('approveRentalUnitReturn', $booking);
        $updated = $this->bookingService->approveRentalUnitReturn($booking);
        return new BookingResource($updated->load($this->bookingRelations));
    }

    public function rejectRentalUnitReturn(RejectRentalUnitReturnRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('approveRentalUnitReturn', $booking);
        $updated = $this->bookingService->rejectRentalUnitReturn(
            $booking,
            $request->validated()['rejection_note'] ?? null
        );
        return new BookingResource($updated->load($this->bookingRelations));
    }
}
