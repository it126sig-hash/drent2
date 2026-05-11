<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Requests\UpdatePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Services\PaymentAccountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentAccountController extends Controller
{
    use AuthorizesRequests;

    protected $service;

    public function __construct(PaymentAccountService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentAccount::class);
        $accounts = $this->service->getAll($request->all());
        return PaymentAccountResource::collection($accounts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentAccountRequest $request)
    {
        $this->authorize('create', PaymentAccount::class);
        $account = $this->service->create($request->validated());
        return new PaymentAccountResource($account);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentAccount $paymentAccount)
    {
        $this->authorize('view', $paymentAccount);
        return new PaymentAccountResource($paymentAccount);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount)
    {
        $this->authorize('update', $paymentAccount);
        $paymentAccount = $this->service->update($paymentAccount, $request->validated());
        return new PaymentAccountResource($paymentAccount);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentAccount $paymentAccount)
    {
        $this->authorize('delete', $paymentAccount);
        $this->service->delete($paymentAccount);
        return response()->noContent();
    }
}
