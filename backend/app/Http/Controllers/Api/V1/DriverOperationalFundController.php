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
use App\Services\PermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DriverOperationalFundController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private DriverOperationalFundService $service,
        private PermissionService $permissionService
    ) {}

    public function bookings(Request $request)
    {
        $this->authorize('viewAny', DriverOperationalFund::class);
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.operational_cost'),
            403
        );

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
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.operational_cost'),
            403
        );

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

    public function storeBookingExpense(StoreDriverOperationalExpenseRequest $request, Booking $booking): DriverOperationalExpenseResource
    {
        $this->authorize('manageExpense', DriverOperationalFund::class); // Generic authorization, or you can use Booking Policy
        
        $expense = $this->service->createBookingExpense(
            $booking,
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

    public function markOperationalComplete(Booking $booking)
    {
        $this->authorize('create', DriverOperationalFund::class);
        $this->service->markOperationalComplete($booking);
        
        return response()->json([
            'message' => 'Operasional ditandai selesai',
        ]);
    }

    public function requestRevertOperational(Request $request, Booking $booking)
    {
        $this->authorize('create', DriverOperationalFund::class);
        $validated = $request->validate([
            'reason' => 'required|string|min:3',
        ]);
        $booking = $this->service->requestOperationalRevert($booking, $validated['reason']);
        
        return response()->json([
            'message' => 'Request aktifkan kembali operasional berhasil dikirim',
            'booking' => $booking,
        ]);
    }

    public function approveRevertOperational(Booking $booking)
    {
        // Require supervisor or superadmin
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $booking = $this->service->approveOperationalRevert($booking);
        
        return response()->json([
            'message' => 'Request aktifkan kembali operasional disetujui',
            'booking' => $booking,
        ]);
    }

    public function rejectRevertOperational(Request $request, Booking $booking)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $validated = $request->validate([
            'rejection_note' => 'nullable|string',
        ]);
        $booking = $this->service->rejectOperationalRevert($booking, $validated['rejection_note'] ?? null);
        
        return response()->json([
            'message' => 'Request aktifkan kembali operasional ditolak',
            'booking' => $booking,
        ]);
    }

    public function voidFund(Request $request, DriverOperationalFund $fund)
    {
        $this->authorize('review', $fund);
        $validated = $request->validate([
            'void_reason' => 'required|string|min:3',
        ]);

        $fund = $this->service->voidFund($fund, $validated['void_reason']);

        return new DriverOperationalFundResource($fund);
    }

    public function voidExpense(Request $request, DriverOperationalExpense $expense)
    {
        $expense->load('fund.driver');
        $this->authorize('review', $expense->fund ?? DriverOperationalFund::class);
        $validated = $request->validate([
            'void_reason' => 'required|string|min:3',
        ]);

        $expense = $this->service->requestVoidExpense($expense, $validated['void_reason']);

        return new DriverOperationalExpenseResource($expense);
    }

    public function approveVoidExpense(DriverOperationalExpense $expense)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $expense->load('fund.driver');
        $expense = $this->service->approveVoidExpense($expense);

        return new DriverOperationalExpenseResource($expense);
    }

    public function rejectVoidExpense(Request $request, DriverOperationalExpense $expense)
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'supervisor'], true), 403);
        $expense->load('fund.driver');
        $validated = $request->validate([
            'rejection_note' => 'nullable|string',
        ]);

        $expense = $this->service->rejectVoidExpense($expense, $validated['rejection_note'] ?? null);

        return new DriverOperationalExpenseResource($expense);
    }
}
