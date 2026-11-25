<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseFleetCardFundRequest;
use App\Http\Requests\RejectFleetCardExpenseRequest;
use App\Http\Requests\StoreFleetCardExpenseRequest;
use App\Http\Requests\StoreFleetCardFundRequest;
use App\Http\Requests\StoreFleetCardRequest;
use App\Http\Requests\UpdateFleetCardStatusRequest;
use App\Http\Resources\FleetCardBookingDetailResource;
use App\Http\Resources\FleetCardExpenseResource;
use App\Http\Resources\FleetCardFundResource;
use App\Http\Resources\FleetCardResource;
use App\Models\BookingDetail;
use App\Models\FleetCard;
use App\Models\FleetCardExpense;
use App\Models\FleetCardFund;
use App\Services\FleetCardService;
use App\Services\PermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetCardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private FleetCardService $service,
        private PermissionService $permissionService
    ) {}

    public function cards(Request $request)
    {
        $this->authorize('viewAny', FleetCardFund::class);

        return FleetCardResource::collection($this->service->listCards($request->all()));
    }

    public function storeCard(StoreFleetCardRequest $request)
    {
        $this->authorize('create', FleetCardFund::class);

        return new FleetCardResource($this->service->createCard($request->validated()));
    }

    public function updateCardStatus(UpdateFleetCardStatusRequest $request, FleetCard $card)
    {
        $this->authorize('review', FleetCardFund::class);
        $validated = $request->validated();

        return new FleetCardResource(
            $this->service->updateCardStatus($card, $validated['status'], $validated['notes'] ?? null)
        );
    }

    public function bookingDetails(Request $request)
    {
        $this->authorize('viewAny', FleetCardFund::class);
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.fleet_card'),
            403
        );

        return FleetCardBookingDetailResource::collection(
            $this->service->eligibleBookingDetails($request->all())
        );
    }

    public function show(FleetCardFund $fund): FleetCardFundResource
    {
        $fund->load('driver');
        $this->authorize('view', $fund);

        return new FleetCardFundResource($fund->load($this->service->fundRelations()));
    }

    public function store(StoreFleetCardFundRequest $request, BookingDetail $bookingDetail): FleetCardFundResource
    {
        $this->authorize('create', FleetCardFund::class);

        $fund = $this->service->createFund($bookingDetail, $request->validated());

        return new FleetCardFundResource($fund);
    }

    public function close(CloseFleetCardFundRequest $request, FleetCardFund $fund): FleetCardFundResource
    {
        $fund->load('driver');
        $this->authorize('review', $fund);

        return new FleetCardFundResource(
            $this->service->closeFund($fund, $request->validated()['close_note'] ?? null)
        );
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', FleetCardFund::class);
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.fleet_card'),
            403
        );

        return response()->json($this->service->history($request->all()));
    }

    public function accept(FleetCardFund $fund): FleetCardFundResource
    {
        $fund->load('driver');
        $this->authorize('accept', $fund);

        return new FleetCardFundResource($this->service->acceptFund($fund));
    }

    public function storeExpense(StoreFleetCardExpenseRequest $request, FleetCardFund $fund): FleetCardExpenseResource
    {
        $fund->load('driver');
        $this->authorize('manageExpense', $fund);

        $expense = $this->service->createExpense(
            $fund,
            $request->validated(),
            $request->file('photo')
        );

        return new FleetCardExpenseResource($expense);
    }

    public function approveExpense(FleetCardExpense $expense): FleetCardExpenseResource
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund);

        return new FleetCardExpenseResource($this->service->approveExpense($expense));
    }

    public function rejectExpense(RejectFleetCardExpenseRequest $request, FleetCardExpense $expense): FleetCardExpenseResource
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund);

        return new FleetCardExpenseResource(
            $this->service->rejectExpense($expense, $request->validated()['rejection_reason'])
        );
    }

    public function showExpensePhoto(FleetCardExpense $expense)
    {
        $expense->load('fund.driver');
        $this->authorize('view', $expense->fund);

        abort_unless($expense->photo_path && Storage::disk('public')->exists($expense->photo_path), 404);

        return response()->file(Storage::disk('public')->path($expense->photo_path));
    }

    public function driverFunds(Request $request)
    {
        $this->authorize('viewAny', FleetCardFund::class);

        abort_unless(auth()->user()?->role === 'driver_tetap', 403);

        return FleetCardFundResource::collection($this->service->driverFunds($request->all()));
    }

    public function voidFund(Request $request, FleetCardFund $fund)
    {
        $this->authorize('review', $fund);
        $validated = $request->validate([
            'void_reason' => 'required|string|min:3',
        ]);

        $fund = $this->service->requestVoidFund($fund, $validated['void_reason']);

        return new FleetCardFundResource($fund);
    }

    public function approveVoidFund(FleetCardFund $fund)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $fund = $this->service->approveVoidFund($fund);

        return new FleetCardFundResource($fund);
    }

    public function rejectVoidFund(Request $request, FleetCardFund $fund)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $validated = $request->validate([
            'rejection_note' => 'nullable|string',
        ]);

        $fund = $this->service->rejectVoidFund($fund, $validated['rejection_note'] ?? null);

        return new FleetCardFundResource($fund);
    }

    public function voidExpense(Request $request, FleetCardExpense $expense)
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund ?? FleetCardFund::class);
        $validated = $request->validate([
            'void_reason' => 'required|string|min:3',
        ]);

        $expense = $this->service->requestVoidExpense($expense, $validated['void_reason']);

        return new FleetCardExpenseResource($expense);
    }

    public function approveVoidExpense(FleetCardExpense $expense)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $expense->load('fund.driver');
        $expense = $this->service->approveVoidExpense($expense);

        return new FleetCardExpenseResource($expense);
    }

    public function rejectVoidExpense(Request $request, FleetCardExpense $expense)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $expense->load('fund.driver');
        $validated = $request->validate([
            'rejection_note' => 'nullable|string',
        ]);

        $expense = $this->service->rejectVoidExpense($expense, $validated['rejection_note'] ?? null);

        return new FleetCardExpenseResource($expense);
    }
}
