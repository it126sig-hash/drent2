<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBookingInvoiceListResource;
use App\Http\Resources\PublicBookingProgressResource;
use App\Services\ReceivableService;

class BookingPublicController extends Controller
{
    public function __construct(private ReceivableService $receivableService)
    {
    }

    public function progress(string $token): PublicBookingProgressResource
    {
        return new PublicBookingProgressResource($this->receivableService->publicBookingProgress($token));
    }

    public function invoices(string $token)
    {
        return PublicBookingInvoiceListResource::collection($this->receivableService->publicBookingInvoices($token));
    }
}
