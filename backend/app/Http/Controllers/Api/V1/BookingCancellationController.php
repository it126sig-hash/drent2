<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayRefundRequest;
use App\Http\Resources\BookingCancellationResource;
use App\Models\BookingCancellation;
use App\Services\BookingCancellationService;
use Illuminate\Http\Request;

class BookingCancellationController extends Controller
{
    public function __construct(private BookingCancellationService $service)
    {
    }

    public function index(Request $request)
    {
        $cancellations = $this->service->getAll($request->only([
            'branch_id',
            'ada_refund',
            'sudah_bayar_refund',
            'per_page',
        ]));

        return BookingCancellationResource::collection($cancellations);
    }

    public function show(BookingCancellation $bookingCancellation)
    {
        $bookingCancellation->load(['booking.customer', 'paymentAccount', 'creator', 'dibayarOleh']);

        return new BookingCancellationResource($bookingCancellation);
    }

    public function payRefund(PayRefundRequest $request, BookingCancellation $bookingCancellation)
    {
        $cancellation = $this->service->payRefund(
            $bookingCancellation,
            (int) $request->validated('payment_account_id')
        );

        return new BookingCancellationResource($cancellation);
    }
}
