<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOtherPaymentAccountTransactionRequest;
use App\Http\Requests\StorePaymentAccountAdjustmentRequest;
use App\Http\Requests\StorePaymentAccountTransferRequest;
use App\Http\Resources\PaymentAccountTransactionResource;
use App\Models\PaymentAccountTransaction;
use App\Services\PaymentAccountTransactionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PaymentAccountTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PaymentAccountTransactionService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentAccountTransaction::class);

        return PaymentAccountTransactionResource::collection($this->service->getAll($request->all()));
    }

    public function transfer(StorePaymentAccountTransferRequest $request)
    {
        $this->authorize('create', PaymentAccountTransaction::class);

        return PaymentAccountTransactionResource::collection(collect($this->service->transfer($request->validated())));
    }

    public function other(StoreOtherPaymentAccountTransactionRequest $request)
    {
        $this->authorize('create', PaymentAccountTransaction::class);

        return new PaymentAccountTransactionResource($this->service->other($request->validated()));
    }

    public function adjust(StorePaymentAccountAdjustmentRequest $request)
    {
        $this->authorize('create', PaymentAccountTransaction::class);

        return new PaymentAccountTransactionResource($this->service->adjustment($request->validated()));
    }
}
