<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Driver;
use App\Models\DriverBalanceLedger;
use App\Models\DriverOperationalExpense;
use App\Models\DriverOperationalFund;
use App\Models\CostType;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverOperationalFundService
{
    private array $fundRelations = [
        'booking.customer',
        'bookingDetail.unit',
        'driver',
        'paymentAccount',
        'creator',
        'closedBy',
        'booking.operationalFunds.driver',
        'booking.operationalFunds.bookingDetail.unit',
        'booking.operationalFunds.paymentAccount',
        'booking.operationalFunds.creator',
        'booking.operationalFunds.items.costType',
        'booking.operationalFunds.expenses.costType',
        'booking.operationalFunds.expenses.submitter',
        'booking.operationalFunds.expenses.reviewer',
        'items.costType',
        'expenses.costType',
        'expenses.submitter',
        'expenses.reviewer',
    ];

    public function eligibleBookings(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = Booking::query()
            ->with([
                'customer',
                'bookingDetails.driver',
                'bookingDetails.unit',
                'bookingDetails.costs.costType',
                'operationalFunds.driver',
                'operationalFunds.paymentAccount',
                'operationalFunds.creator',
                'operationalFunds.bookingDetail.unit',
                'operationalFunds.items.costType',
                'operationalFunds.expenses.costType',
                'operationalFunds.expenses.submitter',
                'operationalFunds.expenses.reviewer',
            ])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->whereHas('bookingDetails', function ($detail) {
                $detail->where('pricing_mode', 'all_in')
                    ->orWhereHas('costs', fn ($cost) => $cost->where('type', 'biaya'));
            })
            ->when($filters['driver_id'] ?? null, fn ($q, $driverId) =>
                $q->whereHas('bookingDetails', fn ($detail) => $detail->where('driver_id', $driverId))
            )
            ->when($filters['status'] ?? null, fn ($q, $status) =>
                $q->whereHas('operationalFunds', fn ($fund) => $fund->where('status', $status))
            )
            ->when(($filters['operational_state'] ?? null) === 'active', fn ($q) =>
                $q->where(function ($query) {
                    $query->whereDoesntHave('operationalFunds')
                        ->orWhereHas('operationalFunds', fn ($fund) =>
                            $fund->whereIn('status', ['pending_driver_acceptance', 'accepted'])
                        );
                })
            )
            ->when(($filters['operational_state'] ?? null) === 'completed', fn ($q) =>
                $q->whereHas('operationalFunds', fn ($fund) => $fund->where('status', 'closed'))
                    ->whereDoesntHave('operationalFunds', fn ($fund) =>
                        $fund->whereIn('status', ['pending_driver_acceptance', 'accepted'])
                    )
            )
            ->when($filters['date_from'] ?? null, fn ($q, $date) =>
                $q->whereHas('bookingDetails', fn ($detail) => $detail->whereDate('tgl_sewa', '>=', $date))
            )
            ->when($filters['date_to'] ?? null, fn ($q, $date) =>
                $q->whereHas('bookingDetails', fn ($detail) => $detail->whereDate('tgl_sewa', '<=', $date))
            )
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('kode_booking', 'like', "%{$search}%")
                        ->orWhere('tujuan', 'like', "%{$search}%")
                        ->orWhere('kota', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('bookingDetails.driver', fn ($driver) => $driver->where('nama', 'like', "%{$search}%"));
                });
            })
            ->latest();

        $bookings = $query->paginate($filters['per_page'] ?? 15);
        $bookings->getCollection()->transform(fn ($booking) => $this->attachBookingSummary($booking));

        return $bookings;
    }

    public function createFund(Booking $booking, array $data): DriverOperationalFund
    {
        $this->assertCanUseBooking($booking);

        return DB::transaction(function () use ($booking, $data) {
            $driver = Driver::where('tenant_id', $booking->tenant_id)->findOrFail($data['driver_id']);
            $detail = null;

            if (!empty($data['booking_detail_id'])) {
                $detail = BookingDetail::where('booking_id', $booking->id)->findOrFail($data['booking_detail_id']);
            }

            if ($detail && (int) $detail->driver_id !== (int) $driver->id) {
                throw new \InvalidArgumentException('Driver tidak sesuai dengan detail booking yang dipilih.');
            }

            if ($driver->branch_id !== $booking->branch_id && Auth::user()->role !== 'superadmin') {
                throw new \InvalidArgumentException('Driver tidak berada di cabang booking ini.');
            }

            $paymentAccount = PaymentAccount::query()
                ->where('tenant_id', $booking->tenant_id)
                ->findOrFail($data['payment_account_id']);

            if ($paymentAccount->branch_id !== $booking->branch_id && Auth::user()->role !== 'superadmin') {
                throw new \InvalidArgumentException('Rekening sumber tidak berada di cabang booking ini.');
            }

            $fundType = $data['fund_type'] ?? $this->inferFundType($data['items']);
            $this->assertFundTypeMatchesItems($fundType, $data['items']);
            $isOperationalWithoutLogin = $fundType === 'operational' && ! $driver->user_id;

            $itemTotal = collect($data['items'])->sum(fn ($item) => (int) $item['planned_amount']);
            if ($itemTotal !== (int) $data['amount']) {
                throw new \InvalidArgumentException('Total breakdown biaya harus sama dengan nominal dana.');
            }

            $fund = DriverOperationalFund::create([
                'tenant_id' => $booking->tenant_id,
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail?->id,
                'driver_id' => $driver->id,
                'payment_account_id' => $data['payment_account_id'],
                'fund_type' => $fundType,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'recipient_destination' => $data['recipient_destination'],
                'notes' => $data['notes'] ?? null,
                'status' => $fundType === 'salary' ? 'closed' : ($isOperationalWithoutLogin ? 'accepted' : 'pending_driver_acceptance'),
                'accepted_at' => $isOperationalWithoutLogin ? now() : null,
                'accepted_by' => $isOperationalWithoutLogin ? Auth::id() : null,
                'closed_at' => $fundType === 'salary' ? now() : null,
                'closed_by' => $fundType === 'salary' ? Auth::id() : null,
                'close_note' => $fundType === 'salary' ? ($data['notes'] ?? null) : null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $item) {
                $fund->items()->create([
                    'cost_type_id' => $item['cost_type_id'] ?? null,
                    'label' => $item['label'],
                    'planned_amount' => $item['planned_amount'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($isOperationalWithoutLogin) {
                $this->applyDriverBalance(
                    driverId: $driver->id,
                    direction: 'credit',
                    amount: (int) $fund->amount,
                    bookingId: $fund->booking_id,
                    fundId: $fund->id,
                    expenseId: null,
                    description: 'Dana operasional diterima tanpa ACC driver'
                );
            }

            if ($fundType === 'operational' && $driver->user_id) {
                $this->notifyUser($driver->user_id, 'operational_fund_created', 'Dana operasional baru', "{$booking->kode_booking} menunggu ACC dana operasional.", [
                    'fund_id' => $fund->id,
                    'booking_id' => $booking->id,
                ], $fund);
            }

            return $fund->fresh($this->fundRelations);
        });
    }

    public function closeFund(DriverOperationalFund $fund, ?string $note = null): DriverOperationalFund
    {
        return DB::transaction(function () use ($fund, $note) {
            $fund = DriverOperationalFund::query()->lockForUpdate()->findOrFail($fund->id);

            if (($fund->fund_type ?? 'operational') === 'salary') {
                throw new \InvalidArgumentException('Gaji driver sudah otomatis closed.');
            }

            if ($fund->status !== 'accepted') {
                throw new \InvalidArgumentException('Hanya dana operasional yang sudah diterima driver yang bisa di-close manual.');
            }

            $fund->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'close_note' => $note,
            ]);

            return $fund->fresh($this->fundRelations);
        });
    }

    public function history(array $filters = []): Paginator
    {
        $user = Auth::user();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        $funds = DriverOperationalFund::query()
            ->with(['booking.customer', 'driver', 'paymentAccount', 'creator'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('recipient_destination', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn ($booking) => $booking->where('kode_booking', 'like', "%{$search}%"))
                        ->orWhereHas('driver', fn ($driver) => $driver->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('paid_at', '<=', $date))
            ->get()
            ->map(fn ($fund) => [
                'id' => 'fund-' . $fund->id,
                'type' => 'transfer',
                'label' => ($fund->fund_type ?? 'operational') === 'salary' ? 'Transfer Gaji Driver' : 'Transfer Dana Operasional',
                'booking_id' => $fund->booking_id,
                'booking_code' => $fund->booking?->kode_booking,
                'customer_name' => $fund->booking?->customer?->nama,
                'driver_name' => $fund->driver?->nama,
                'amount' => (int) $fund->amount,
                'direction' => 'out',
                'status' => $fund->status,
                'happened_at' => $fund->paid_at?->format('Y-m-d') ?? $fund->created_at?->toISOString(),
                'payment_account' => $fund->paymentAccount ? [
                    'id' => $fund->paymentAccount->id,
                    'nama_bank' => $fund->paymentAccount->nama_bank,
                    'nomor_rekening' => $fund->paymentAccount->nomor_rekening,
                    'atas_nama' => $fund->paymentAccount->atas_nama,
                ] : null,
                'description' => $fund->recipient_destination,
                'created_by_name' => $fund->creator?->name,
            ]);

        $expenses = DriverOperationalExpense::query()
            ->with(['booking.customer', 'driver', 'costType', 'fund.paymentAccount', 'submitter', 'reviewer'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn ($booking) => $booking->where('kode_booking', 'like', "%{$search}%"))
                        ->orWhereHas('driver', fn ($driver) => $driver->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->get()
            ->map(fn ($expense) => [
                'id' => 'expense-' . $expense->id,
                'type' => 'expense',
                'expense_type' => $expense->type,
                'label' => $expense->type === 'return' ? 'Pengembalian Sisa Dana' : 'Pembayaran Bon',
                'booking_id' => $expense->booking_id,
                'booking_code' => $expense->booking?->kode_booking,
                'customer_name' => $expense->booking?->customer?->nama,
                'driver_name' => $expense->driver?->nama,
                'amount' => (int) $expense->amount,
                'direction' => 'in',
                'status' => $expense->status,
                'happened_at' => $expense->reviewed_at?->toISOString() ?? $expense->created_at?->toISOString(),
                'payment_account' => $expense->fund?->paymentAccount ? [
                    'id' => $expense->fund->paymentAccount->id,
                    'nama_bank' => $expense->fund->paymentAccount->nama_bank,
                    'nomor_rekening' => $expense->fund->paymentAccount->nomor_rekening,
                    'atas_nama' => $expense->fund->paymentAccount->atas_nama,
                ] : null,
                'description' => $expense->description,
                'created_by_name' => $expense->submitter?->name,
                'reviewed_by_name' => $expense->reviewer?->name,
                'cost_type_name' => $expense->costType?->nama,
            ]);

        $items = $funds
            ->merge($expenses)
            ->sortByDesc(fn ($item) => strtotime($item['happened_at'] ?? '1970-01-01'))
            ->values();

        return new Paginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function acceptFund(DriverOperationalFund $fund): DriverOperationalFund
    {
        return DB::transaction(function () use ($fund) {
            $fund = DriverOperationalFund::query()->lockForUpdate()->findOrFail($fund->id);

            if ($fund->status !== 'pending_driver_acceptance') {
                throw new \InvalidArgumentException('Dana operasional ini sudah diproses.');
            }

            if (($fund->fund_type ?? 'operational') === 'salary') {
                throw new \InvalidArgumentException('Gaji driver tidak perlu di-ACC dan tidak masuk saldo operasional.');
            }

            $fund->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => Auth::id(),
            ]);

            $this->applyDriverBalance(
                driverId: $fund->driver_id,
                direction: 'credit',
                amount: (int) $fund->amount,
                bookingId: $fund->booking_id,
                fundId: $fund->id,
                expenseId: null,
                description: 'Dana operasional diterima driver'
            );

            $this->notifyFinance($fund, 'operational_fund_accepted', 'Dana operasional diterima', "{$fund->booking?->kode_booking} sudah di-ACC driver.");

            return $fund->fresh($this->fundRelations);
        });
    }

    public function createExpense(DriverOperationalFund $fund, array $data, mixed $photo = null): DriverOperationalExpense
    {
        return DB::transaction(function () use ($fund, $data, $photo) {
            $fund = DriverOperationalFund::query()->with('booking', 'driver')->lockForUpdate()->findOrFail($fund->id);

            if ($fund->status !== 'accepted') {
                throw new \InvalidArgumentException('Bon hanya bisa diinput setelah dana diterima driver.');
            }

            if (($fund->fund_type ?? 'operational') === 'salary') {
                throw new \InvalidArgumentException('Gaji driver tidak membutuhkan bon atau pengembalian.');
            }

            $user = Auth::user();
            $isFinanceSource = in_array($user->role, ['superadmin', 'admin_branch', 'finance'], true);
            $photoPath = $photo ? $photo->store('driver-operational-receipts', 'public') : null;

            $expense = DriverOperationalExpense::create([
                'tenant_id' => $fund->tenant_id,
                'branch_id' => $fund->branch_id,
                'driver_operational_fund_id' => $fund->id,
                'booking_id' => $fund->booking_id,
                'booking_detail_id' => $fund->booking_detail_id,
                'driver_id' => $fund->driver_id,
                'cost_type_id' => $data['type'] === 'return' ? null : ($data['cost_type_id'] ?? null),
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'photo_path' => $photoPath,
                'status' => $isFinanceSource ? 'approved' : 'submitted',
                'source' => $isFinanceSource ? 'finance' : 'driver',
                'submitted_by' => Auth::id(),
                'reviewed_by' => $isFinanceSource ? Auth::id() : null,
                'reviewed_at' => $isFinanceSource ? now() : null,
            ]);

            if ($isFinanceSource) {
                $this->applyExpenseDebit($expense, 'Bon operasional diinput finance');
            } else {
                $this->notifyFinance($fund, 'operational_expense_submitted', 'Bon driver menunggu review', "{$fund->booking?->kode_booking} memiliki bon baru dari driver.");
            }

            return $expense->fresh(['costType', 'submitter', 'reviewer']);
        });
    }

    public function approveExpense(DriverOperationalExpense $expense): DriverOperationalExpense
    {
        return DB::transaction(function () use ($expense) {
            $expense = DriverOperationalExpense::query()
                ->with('fund.booking', 'driver')
                ->lockForUpdate()
                ->findOrFail($expense->id);

            if ($expense->status !== 'submitted') {
                throw new \InvalidArgumentException('Hanya bon berstatus submitted yang bisa disetujui.');
            }

            $expense->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->applyExpenseDebit($expense, 'Bon/pengembalian operasional disetujui finance');

            if ($expense->driver?->user_id) {
                $this->notifyUser($expense->driver->user_id, 'operational_expense_approved', 'Bon disetujui', 'Bon operasional kamu sudah disetujui finance.', [
                    'expense_id' => $expense->id,
                    'fund_id' => $expense->driver_operational_fund_id,
                ], $expense->fund);
            }

            return $expense->fresh(['costType', 'submitter', 'reviewer']);
        });
    }

    public function rejectExpense(DriverOperationalExpense $expense, string $reason): DriverOperationalExpense
    {
        $expense->load('fund.booking', 'driver');

        if ($expense->status !== 'submitted') {
            throw new \InvalidArgumentException('Hanya bon berstatus submitted yang bisa ditolak.');
        }

        $expense->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if ($expense->driver?->user_id) {
            $this->notifyUser($expense->driver->user_id, 'operational_expense_rejected', 'Bon perlu diperbaiki', $reason, [
                'expense_id' => $expense->id,
                'fund_id' => $expense->driver_operational_fund_id,
            ], $expense->fund);
        }

        return $expense->fresh(['costType', 'submitter', 'reviewer']);
    }

    public function driverFunds(array $filters = []): LengthAwarePaginator
    {
        $driver = $this->currentDriver();

        return DriverOperationalFund::query()
            ->with($this->fundRelations)
            ->where('driver_id', $driver->id)
            ->where('fund_type', 'operational')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function driverSchedules(array $filters = []): LengthAwarePaginator
    {
        $driver = $this->currentDriver();

        return BookingDetail::query()
            ->with(['booking.customer', 'unit'])
            ->where('driver_id', $driver->id)
            ->when($filters['period'] ?? null, function ($q, $period) {
                if ($period === 'past') {
                    $q->where('tgl_kembali', '<', now());
                } elseif ($period === 'upcoming') {
                    $q->where('tgl_kembali', '>=', now());
                }
            })
            ->orderBy('tgl_sewa')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function fundRelations(): array
    {
        return $this->fundRelations;
    }

    private function assertCanUseBooking(Booking $booking): void
    {
        $user = Auth::user();

        if ($user->tenant_id !== $booking->tenant_id) {
            throw new \InvalidArgumentException('Booking tidak valid untuk tenant ini.');
        }

        if ($user->role !== 'superadmin' && $user->branch_id !== $booking->branch_id) {
            throw new \InvalidArgumentException('Booking tidak valid untuk cabang ini.');
        }
    }

    private function currentDriver(): Driver
    {
        $driver = Driver::where('user_id', Auth::id())->first();

        if (! $driver) {
            abort(422, 'Akun driver belum terhubung ke data driver. Buka Manajemen User, edit akun ini, lalu pilih data driver tetap yang sesuai.');
        }

        return $driver;
    }

    private function applyExpenseDebit(DriverOperationalExpense $expense, string $description): void
    {
        $this->applyDriverBalance(
            driverId: $expense->driver_id,
            direction: 'debit',
            amount: (int) $expense->amount,
            bookingId: $expense->booking_id,
            fundId: $expense->driver_operational_fund_id,
            expenseId: $expense->id,
            description: $description
        );
    }

    private function applyDriverBalance(
        int $driverId,
        string $direction,
        int $amount,
        ?int $bookingId,
        ?int $fundId,
        ?int $expenseId,
        string $description
    ): void {
        $driver = Driver::query()->lockForUpdate()->findOrFail($driverId);
        $before = (int) $driver->saldo;
        $after = $direction === 'credit' ? $before + $amount : $before - $amount;

        if ($after < 0) {
            throw new \InvalidArgumentException('Saldo driver tidak cukup untuk transaksi ini.');
        }

        $driver->update(['saldo' => $after]);

        DriverBalanceLedger::create([
            'tenant_id' => $driver->tenant_id,
            'branch_id' => $driver->branch_id,
            'driver_id' => $driver->id,
            'booking_id' => $bookingId,
            'driver_operational_fund_id' => $fundId,
            'driver_operational_expense_id' => $expenseId,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
            'created_by' => Auth::id(),
        ]);
    }

    private function attachBookingSummary(Booking $booking): Booking
    {
        $bookingOperationalTotal = 0;
        $allInTotal = 0;

        foreach ($booking->bookingDetails as $detail) {
            $bookingOperationalTotal += $detail->costs
                ->where('type', 'biaya')
                ->sum(fn ($cost) => (int) $cost->amount);

            if ($detail->pricing_mode === 'all_in') {
                $allInTotal += ((int) ($detail->harga_all_in ?? 0)) * ((int) ($detail->lama_sewa ?? $booking->lama_sewa ?? 1));
            }
        }

        $funds = $booking->operationalFunds;
        $operationalFunds = $funds->where('fund_type', 'operational');
        $salaryFunds = $funds->where('fund_type', 'salary');
        $financeDisbursedTotal = $funds
            ->where('fund_type', 'operational')
            ->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed'])
            ->sum(fn ($fund) => (int) $fund->amount);
        $driverSalaryTotal = $salaryFunds
            ->where('status', 'closed')
            ->sum(fn ($fund) => (int) $fund->amount);
        $approvedExpenseTotal = $operationalFunds->sum(fn ($fund) => $fund->approvedExpenseTotal());
        $approvedReturnTotal = $operationalFunds->sum(fn ($fund) => $fund->approvedReturnTotal());

        $booking->booking_operational_total = $bookingOperationalTotal;
        $booking->all_in_total = $allInTotal;
        $booking->finance_disbursed_total = $financeDisbursedTotal;
        $booking->driver_salary_total = $driverSalaryTotal;
        $booking->approved_expense_total = $approvedExpenseTotal;
        $booking->approved_return_total = $approvedReturnTotal;
        $booking->pending_review_count = $operationalFunds->sum(fn ($fund) => $fund->pendingReviewCount());
        $booking->pending_driver_review_count = $operationalFunds->sum(fn ($fund) => $fund->pendingDriverReviewCount());
        $booking->pending_driver_acceptance_count = $operationalFunds
            ->where('status', 'pending_driver_acceptance')
            ->count();
        $booking->remaining_amount = max(0, $financeDisbursedTotal - $approvedExpenseTotal - $approvedReturnTotal);
        $booking->active_fund_count = $operationalFunds->whereIn('status', ['pending_driver_acceptance', 'accepted'])->count();
        $booking->closed_fund_count = $funds->where('status', 'closed')->count();

        return $booking;
    }

    private function inferFundType(array $items): string
    {
        $costTypeIds = collect($items)->pluck('cost_type_id')->filter()->unique()->values();

        if ($costTypeIds->isEmpty()) {
            return 'operational';
        }

        $costTypes = CostType::query()
            ->whereIn('id', $costTypeIds)
            ->pluck('kode', 'id');

        return $costTypes->isNotEmpty()
            && $costTypes->every(fn ($kode) => $kode === 'driver')
            ? 'salary'
            : 'operational';
    }

    private function assertFundTypeMatchesItems(string $fundType, array $items): void
    {
        $costTypeIds = collect($items)->pluck('cost_type_id')->filter()->unique()->values();
        $costTypes = CostType::query()
            ->whereIn('id', $costTypeIds)
            ->get(['id', 'kode']);

        $hasDriverCost = $costTypes->contains(fn ($type) => $type->kode === 'driver');
        $hasNonDriverCost = $costTypes->contains(fn ($type) => $type->kode !== 'driver');

        if ($fundType === 'salary') {
            if (! $hasDriverCost || $hasNonDriverCost) {
                throw new \InvalidArgumentException('Gaji driver harus memakai cost type Driver dan tidak boleh dicampur dengan biaya operasional lain.');
            }

            return;
        }

        if ($hasDriverCost) {
            throw new \InvalidArgumentException('Cost type Driver adalah gaji. Gunakan tombol Bayar Gaji Driver, bukan dana operasional.');
        }
    }

    private function notifyFinance(DriverOperationalFund $fund, string $type, string $title, string $message): void
    {
        $users = User::query()
            ->where('tenant_id', $fund->tenant_id)
            ->whereIn('role', ['superadmin', 'admin_branch', 'finance'])
            ->when($fund->branch_id, fn ($q) => $q->where(function ($query) use ($fund) {
                $query->where('role', 'superadmin')->orWhere('branch_id', $fund->branch_id);
            }))
            ->get();

        foreach ($users as $user) {
            $this->notifyUser($user->id, $type, $title, $message, [
                'fund_id' => $fund->id,
                'booking_id' => $fund->booking_id,
            ], $fund);
        }
    }

    private function notifyUser(int $userId, string $type, string $title, ?string $message, array $data, DriverOperationalFund $fund): void
    {
        AppNotification::create([
            'tenant_id' => $fund->tenant_id,
            'branch_id' => $fund->branch_id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
