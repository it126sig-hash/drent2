<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRentToRentBillRequest;
use App\Http\Requests\RejectVoidRentToRentBillRequest;
use App\Http\Requests\RequestVoidRentToRentBillRequest;
use App\Http\Requests\StoreRentToRentPaymentRequest;
use App\Http\Requests\UpdateRentToRentDebtAmountRequest;
use App\Http\Resources\PublicRentToRentBillResource;
use App\Http\Resources\RentToRentBillResource;
use App\Http\Resources\RentToRentDebtResource;
use App\Models\RentToRentBill;
use App\Models\RentToRentDebt;
use App\Models\RentToRentPayment;
use App\Services\RentToRentPdfService;
use App\Services\RentToRentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class RentToRentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private RentToRentService $service, private RentToRentPdfService $pdfService)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', RentToRentBill::class);

        $filters = $this->scopedFilters($request->only([
            'page',
            'per_page',
            'rental_owner_id',
            'status',
            'search',
        ]));

        $result = $this->service->listDebts($filters);

        return RentToRentDebtResource::collection($result['debts'])
            ->additional([
                'summary' => $result['summary'],
                'owner_options' => $result['owner_options'],
            ]);
    }

    public function show(RentToRentDebt $debt): RentToRentDebtResource
    {
        $this->authorize('viewAny', RentToRentBill::class);
        $this->assertDebtScope($debt);

        return new RentToRentDebtResource($this->service->showDebt($debt));
    }

    public function updateAmount(UpdateRentToRentDebtAmountRequest $request, RentToRentDebt $debt): RentToRentDebtResource
    {
        $this->authorize('viewAny', RentToRentBill::class);
        $this->assertDebtScope($debt);

        try {
            return new RentToRentDebtResource($this->service->updateDebtAmount(
                $debt,
                $request->validated('amount_override')
            ));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['amount_override' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function bills(Request $request)
    {
        $this->authorize('viewAny', RentToRentBill::class);

        return RentToRentBillResource::collection($this->service->bills($this->scopedFilters($request->only([
            'page',
            'per_page',
            'rental_owner_id',
            'status',
        ]))));
    }

    public function showBill(RentToRentBill $bill): RentToRentBillResource
    {
        $this->authorize('view', $bill);

        return new RentToRentBillResource($this->service->showBill($bill));
    }

    public function generateBill(GenerateRentToRentBillRequest $request): RentToRentBillResource
    {
        $this->authorize('create', RentToRentBill::class);
        $user = auth()->user();

        try {
            return new RentToRentBillResource($this->service->createBill(
                $request->validated('debt_ids'),
                $user->branch_id,
                $user->tenant_id
            ));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['debt_ids' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function markSent(RentToRentBill $bill): RentToRentBillResource
    {
        $this->authorize('update', $bill);

        return new RentToRentBillResource($this->service->markSent($bill));
    }

    public function billPdf(RentToRentBill $bill)
    {
        $this->authorize('view', $bill);

        return response($this->pdfService->make($this->service->showBill($bill)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bill->bill_number.'.pdf"',
        ]);
    }

    public function storePayment(StoreRentToRentPaymentRequest $request, RentToRentBill $bill): RentToRentBillResource
    {
        $this->authorize('update', $bill);

        try {
            return new RentToRentBillResource($this->service->storePayment($bill, $request->validated()));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['amount' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function storeDebtPayment(StoreRentToRentPaymentRequest $request, RentToRentDebt $debt): RentToRentDebtResource
    {
        $this->authorize('create', RentToRentBill::class);
        $this->assertDebtScope($debt);

        try {
            return new RentToRentDebtResource($this->service->storeDebtPayment($debt, $request->validated()));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['amount' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function markDebtPaid(RentToRentDebt $debt): RentToRentDebtResource
    {
        $this->authorize('create', RentToRentBill::class);
        $this->assertDebtScope($debt);

        try {
            return new RentToRentDebtResource($this->service->markDebtPaid($debt));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function markBillPaid(RentToRentBill $bill): RentToRentBillResource
    {
        $this->authorize('update', $bill);

        try {
            return new RentToRentBillResource($this->service->markBillPaid($bill));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function requestVoidPayment(Request $request, RentToRentPayment $payment)
    {
        $this->authorize('create', RentToRentBill::class);
        $this->assertPaymentScope($payment);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            return response()->json(['data' => $this->service->requestVoidPayment($payment, $validated['void_reason'])]);
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function approveVoidPayment(RentToRentPayment $payment)
    {
        $this->assertPaymentSupervisorScope($payment);

        try {
            return response()->json(['data' => $this->service->approveVoidPayment($payment)]);
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function rejectVoidPayment(Request $request, RentToRentPayment $payment)
    {
        $this->assertPaymentSupervisorScope($payment);

        $validated = $request->validate([
            'void_rejection_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            return response()->json(['data' => $this->service->rejectVoidPayment($payment, $validated['void_rejection_note'] ?? null)]);
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function requestVoid(RequestVoidRentToRentBillRequest $request, RentToRentBill $bill): RentToRentBillResource
    {
        $this->authorize('update', $bill);

        try {
            return new RentToRentBillResource($this->service->requestVoid($bill, $request->validated('void_reason')));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['void_reason' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function approveVoid(RentToRentBill $bill): RentToRentBillResource
    {
        $this->assertBillScope($bill);

        try {
            return new RentToRentBillResource($this->service->approveVoid($bill));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function rejectVoid(RejectVoidRentToRentBillRequest $request, RentToRentBill $bill): RentToRentBillResource
    {
        $this->assertBillScope($bill);

        try {
            return new RentToRentBillResource($this->service->rejectVoid(
                $bill,
                $request->validated('void_rejection_note')
            ));
        } catch (\InvalidArgumentException $exception) {
            abort(response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422));
        }
    }

    public function paymentHistory(Request $request)
    {
        $this->authorize('viewAny', RentToRentBill::class);

        return response()->json([
            'data' => $this->service->paymentHistory($this->scopedFilters($request->only([
                'view',
                'latest_page',
                'latest_per_page',
                'latest_limit',
                'group_page',
                'group_per_page',
                'group_limit',
            ]))),
        ]);
    }

    public function publicBill(string $token): PublicRentToRentBillResource
    {
        return new PublicRentToRentBillResource($this->service->publicBill($token));
    }

    public function publicBillPdf(string $token)
    {
        $bill = $this->service->publicBill($token);

        return response($this->pdfService->make($bill), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bill->bill_number.'.pdf"',
        ]);
    }

    private function scopedFilters(array $filters): array
    {
        $user = auth()->user();
        $filters['tenant_id'] = $user->tenant_id;

        if ($user->role !== 'superadmin') {
            $filters['branch_id'] = $user->branch_id;
        }

        return $filters;
    }

    private function assertDebtScope(RentToRentDebt $debt): void
    {
        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return;
        }

        abort_if($user->branch_id !== $debt->branch_id, 403);
    }

    private function assertBillScope(RentToRentBill $bill): void
    {
        $user = auth()->user();

        abort_unless(in_array($user->role, ['superadmin', 'supervisor'], true), 403);

        if ($user->role === 'superadmin') {
            return;
        }

        abort_if($user->branch_id !== $bill->branch_id, 403);
    }

    private function assertPaymentScope(RentToRentPayment $payment): void
    {
        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return;
        }

        $payment->loadMissing(['bill', 'allocations.debt']);

        $branchId = $payment->bill?->branch_id
            ?? $payment->allocations->pluck('debt.branch_id')->filter()->first();

        abort_if($user->branch_id !== $branchId, 403);
    }

    private function assertPaymentSupervisorScope(RentToRentPayment $payment): void
    {
        $user = auth()->user();

        abort_unless(in_array($user->role, ['superadmin', 'supervisor'], true), 403);

        if ($user->role === 'superadmin') {
            return;
        }

        $payment->loadMissing(['bill', 'allocations.debt']);

        $branchId = $payment->bill?->branch_id
            ?? $payment->allocations->pluck('debt.branch_id')->filter()->first();

        abort_if($user->branch_id !== $branchId, 403);
    }
}
