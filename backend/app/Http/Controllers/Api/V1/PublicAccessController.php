<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSignPhysicalCheckRequest;
use App\Http\Resources\PhysicalCheckItemResource;
use App\Http\Resources\PhysicalCheckResource;
use App\Http\Resources\PublicInvoiceResource;
use App\Http\Resources\PublicRentToRentBillResource;
use App\Services\PhysicalCheckService;
use App\Services\ReceivableService;
use App\Services\RentToRentPdfService;
use App\Services\RentToRentService;
use Illuminate\Http\Request;

/**
 * Satu gerbang untuk semua endpoint publik berbasis token (tanpa auth).
 * URL publik tidak berubah; controller lama tetap memegang endpoint internal.
 */
class PublicAccessController extends Controller
{
    public function __construct(
        private ReceivableService $receivableService,
        private RentToRentService $rentToRentService,
        private RentToRentPdfService $rentToRentPdfService,
        private PhysicalCheckService $physicalCheckService,
    ) {
    }

    public function invoice(string $token): PublicInvoiceResource
    {
        [$invoice, $paymentAccounts] = $this->receivableService->publicInvoice($token);

        return new PublicInvoiceResource($invoice, $paymentAccounts);
    }

    public function rentToRentBill(string $token): PublicRentToRentBillResource
    {
        return new PublicRentToRentBillResource($this->rentToRentService->publicBill($token));
    }

    public function rentToRentBillPdf(string $token)
    {
        $bill = $this->rentToRentService->publicBill($token);

        return response($this->rentToRentPdfService->make($bill), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bill->bill_number.'.pdf"',
        ]);
    }

    public function physicalCheck(string $token)
    {
        $check = $this->physicalCheckService->findPublic($token);
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
                'items' => PhysicalCheckItemResource::collection($this->physicalCheckService->publicItems($check))->resolve(),
            ],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function physicalCheckOtp(string $token, Request $request)
    {
        $check = $this->physicalCheckService->findPublic($token);
        $this->physicalCheckService->requestOtp($check, $request);

        return response()->json([
            'data' => ['sent' => true],
            'message' => 'Kode OTP dikirim ke email penyewa.',
            'errors' => null,
        ]);
    }

    public function physicalCheckActivity(string $token, Request $request)
    {
        $request->validate([
            'event' => ['required', 'string', 'max:80'],
            'context' => ['nullable', 'array'],
        ]);

        $check = $this->physicalCheckService->findPublic($token);
        $this->physicalCheckService->recordActivity($check, $request->input('event'), $request->input('context', []), $request, 'customer');

        return response()->json([
            'data' => ['recorded' => true],
            'message' => 'ok',
            'errors' => null,
        ]);
    }

    public function physicalCheckSubmit(string $token, PublicSignPhysicalCheckRequest $request): PhysicalCheckResource
    {
        $check = $this->physicalCheckService->findPublic($token);
        $stored = $this->physicalCheckService->storePublicSignature($check, $request->validated(), $request);

        return new PhysicalCheckResource($stored);
    }
}
