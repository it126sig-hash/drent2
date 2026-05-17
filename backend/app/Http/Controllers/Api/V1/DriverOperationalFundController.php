<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseDriverOperationalFundRequest;
use App\Http\Requests\RejectDriverOperationalExpenseRequest;
use App\Http\Requests\StoreDriverOperationalExpenseRequest;
use App\Http\Requests\StoreDriverOperationalFundRequest;
use App\Http\Resources\DriverOperationalExpenseResource;
use App\Http\Resources\DriverOperationalFundResource;
use App\Http\Resources\DriverScheduleResource;
use App\Http\Resources\OperationalBookingResource;
use App\Models\Booking;
use App\Models\DriverOperationalExpense;
use App\Models\DriverOperationalFund;
use App\Services\DriverOperationalFundService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DriverOperationalFundController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private DriverOperationalFundService $service)
    {
    }

    public function bookings(Request $request)
    {
        $this->authorize('viewAny', DriverOperationalFund::class);
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin_branch', 'finance'], true), 403);

        return OperationalBookingResource::collection(
            $this->service->eligibleBookings($request->all())
        );
    }

    public function show(DriverOperationalFund $fund): DriverOperationalFundResource
    {
        $fund->load('driver');
        $this->authorize('view', $fund);

        return new DriverOperationalFundResource($fund->load($this->service->fundRelations()));
    }

    public function store(StoreDriverOperationalFundRequest $request, Booking $booking): DriverOperationalFundResource
    {
        $this->authorize('create', DriverOperationalFund::class);

        $fund = $this->service->createFund($booking, $request->validated());

        return new DriverOperationalFundResource($fund);
    }

    public function close(CloseDriverOperationalFundRequest $request, DriverOperationalFund $fund): DriverOperationalFundResource
    {
        $fund->load('driver');
        $this->authorize('review', $fund);

        return new DriverOperationalFundResource(
            $this->service->closeFund($fund, $request->validated()['close_note'] ?? null)
        );
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', DriverOperationalFund::class);
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin_branch', 'finance'], true), 403);

        return response()->json($this->service->history($request->all()));
    }

    public function accept(DriverOperationalFund $fund): DriverOperationalFundResource
    {
        $fund->load('driver');
        $this->authorize('accept', $fund);

        return new DriverOperationalFundResource($this->service->acceptFund($fund));
    }

    public function storeExpense(StoreDriverOperationalExpenseRequest $request, DriverOperationalFund $fund): DriverOperationalExpenseResource
    {
        $fund->load('driver');
        $this->authorize('manageExpense', $fund);

        $expense = $this->service->createExpense(
            $fund,
            $request->validated(),
            $request->file('photo')
        );

        return new DriverOperationalExpenseResource($expense);
    }

    public function approveExpense(DriverOperationalExpense $expense): DriverOperationalExpenseResource
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund);

        return new DriverOperationalExpenseResource($this->service->approveExpense($expense));
    }

    public function rejectExpense(RejectDriverOperationalExpenseRequest $request, DriverOperationalExpense $expense): DriverOperationalExpenseResource
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund);

        return new DriverOperationalExpenseResource(
            $this->service->rejectExpense($expense, $request->validated()['rejection_reason'])
        );
    }

    public function showExpensePhoto(DriverOperationalExpense $expense)
    {
        $expense->load('fund.driver');
        $this->authorize('view', $expense->fund);

        abort_unless($expense->photo_path && Storage::disk('public')->exists($expense->photo_path), 404);

        return response()->file(Storage::disk('public')->path($expense->photo_path));
    }

    public function driverFunds(Request $request)
    {
        $this->authorize('viewAny', DriverOperationalFund::class);

        abort_unless(auth()->user()?->role === 'driver_tetap', 403);

        return DriverOperationalFundResource::collection($this->service->driverFunds($request->all()));
    }

    public function driverSchedules(Request $request)
    {
        abort_unless(auth()->user()?->role === 'driver_tetap', 403);

        return DriverScheduleResource::collection($this->service->driverSchedules($request->all()));
    }
}
