<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\CostType;
use App\Models\Driver;
use App\Models\FleetCard;
use App\Models\FleetCardBalanceLedger;
use App\Models\FleetCardExpense;
use App\Models\FleetCardFund;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FleetCardService
{
    public function __construct(private PaymentAccountTransactionService $transactionService)
    {
    }

    private array $fundRelations = [
        'fleetCard',
        'booking.customer',
        'bookingDetail.unit',
        'driver',
        'paymentAccount',
        'creator',
        'closedBy',
        'items.costType',
        'expenses.costType',
        'expenses.submitter',
        'expenses.reviewer',
    ];

    public function fundRelations(): array
    {
        return $this->fundRelations;
    }

    public function listCards(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        return FleetCard::query()
            ->with(['creator', 'activeFund.driver', 'activeFund.booking'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")->orWhere('label', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createCard(array $data): FleetCard
    {
        $user = Auth::user();

        return FleetCard::create([
            'tenant_id' => $user->tenant_id,
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
            'code' => $data['code'],
            'label' => $data['label'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    public function updateCardStatus(FleetCard $card, string $status, ?string $note = null): FleetCard
    {
        if ($status !== 'active' && $card->activeFund) {
            throw new \InvalidArgumentException('Kartu sedang dipinjam pada booking aktif, tidak bisa dinonaktifkan.');
        }

        $card->update([
            'status' => $status,
            'notes' => $note ?? $card->notes,
        ]);

        return $card->fresh();
    }

    public function eligibleBookingDetails(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = BookingDetail::query()
            ->with([
                'booking.customer',
                'driver',
                'unit',
                'costs.costType',
                'fleetCardFunds.fleetCard',
                'fleetCardFunds.driver',
                'fleetCardExpenses',
            ])
            ->whereHas('booking', fn ($q) => $q->where('tenant_id', $user->tenant_id)
                ->when($user->role !== 'superadmin', fn ($q2) => $q2->where('branch_id', $user->branch_id)))
            ->where(function ($detail) {
                $detail->where('pricing_mode', 'all_in')
                    ->orWhereHas('costs', fn ($cost) => $cost->where('type', 'biaya'));
            })
            ->when($filters['driver_id'] ?? null, fn ($q, $driverId) => $q->where('driver_id', $driverId))
            ->when($filters['fleet_card_id'] ?? null, fn ($q, $cardId) =>
                $q->whereHas('fleetCardFunds', fn ($fund) => $fund->where('fleet_card_id', $cardId))
            )
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('booking', fn ($booking) => $booking->where('kode_booking', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('nama', 'like', "%{$search}%")));
            })
            ->latest('tgl_sewa');

        $details = $query->paginate($filters['per_page'] ?? 15);
        $details->getCollection()->transform(fn ($detail) => $this->attachDetailSummary($detail));

        return $details;
    }

    public function createFund(BookingDetail $detail, array $data): FleetCardFund
    {
        $booking = $detail->booking;
        $this->assertCanUseBooking($booking);

        return DB::transaction(function () use ($booking, $detail, $data) {
            $card = FleetCard::where('tenant_id', $booking->tenant_id)
                ->lockForUpdate()
                ->findOrFail($data['fleet_card_id']);

            if ($card->status !== 'active') {
                throw new \InvalidArgumentException('Kartu fleet tidak aktif.');
            }

            $hasOtherActiveUse = FleetCardFund::where('fleet_card_id', $card->id)
                ->whereIn('status', ['pending_driver_acceptance', 'accepted'])
                ->exists();
            if ($hasOtherActiveUse) {
                throw new \InvalidArgumentException('Kartu fleet ini sedang dipinjam pada booking lain.');
            }

            $hasActiveOnDetail = FleetCardFund::where('booking_detail_id', $detail->id)
                ->whereIn('status', ['pending_driver_acceptance', 'accepted'])
                ->exists();
            if ($hasActiveOnDetail) {
                throw new \InvalidArgumentException('Periode sewa ini sudah memiliki kartu fleet aktif.');
            }

            $driver = Driver::where('tenant_id', $booking->tenant_id)->findOrFail($data['driver_id']);
            if ($driver->branch_id !== $booking->branch_id && Auth::user()->role !== 'superadmin') {
                throw new \InvalidArgumentException('Driver tidak berada di cabang booking ini.');
            }

            $amount = (int) ($data['amount'] ?? 0);
            $carriedOverBalance = (int) $card->saldo;

            $itemTotal = collect($data['items'])->sum(fn ($item) => (int) $item['planned_amount']);
            if ($itemTotal !== $carriedOverBalance + $amount) {
                throw new \InvalidArgumentException('Total breakdown biaya harus sama dengan saldo kartu sisa + top-up baru.');
            }

            $paymentAccount = null;
            if ($amount > 0) {
                $paymentAccount = PaymentAccount::query()
                    ->where('tenant_id', $booking->tenant_id)
                    ->findOrFail($data['payment_account_id']);

                if ($paymentAccount->branch_id !== $booking->branch_id && Auth::user()->role !== 'superadmin') {
                    throw new \InvalidArgumentException('Rekening sumber tidak berada di cabang booking ini.');
                }
            }

            $isDriverWithoutLogin = ! $driver->user_id;

            $fund = FleetCardFund::create([
                'tenant_id' => $booking->tenant_id,
                'branch_id' => $booking->branch_id,
                'fleet_card_id' => $card->id,
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'driver_id' => $driver->id,
                'payment_account_id' => $paymentAccount?->id,
                'amount' => $amount,
                'paid_at' => \App\Helpers\DateHelper::parseDateWithCurrentTime($data['paid_at'] ?? null),
                'recipient_destination' => $data['recipient_destination'],
                'notes' => $data['notes'] ?? null,
                'status' => $isDriverWithoutLogin ? 'accepted' : 'pending_driver_acceptance',
                'accepted_at' => $isDriverWithoutLogin ? now() : null,
                'accepted_by' => $isDriverWithoutLogin ? Auth::id() : null,
                'created_by' => Auth::id(),
            ]);

            if ($amount > 0 && $paymentAccount) {
                $this->transactionService->applyDelta($paymentAccount, -$amount, [
                    'type' => 'fleet_card_topup_out',
                    'amount' => $amount,
                    'description' => "Top-up kartu fleet {$card->code} untuk booking #{$booking->kode_booking}",
                    'created_by' => Auth::id(),
                    'transaction_at' => $fund->paid_at ?? now(),
                ]);
            }

            foreach ($data['items'] as $item) {
                $fund->items()->create([
                    'cost_type_id' => $item['cost_type_id'] ?? null,
                    'label' => $item['label'],
                    'planned_amount' => $item['planned_amount'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($amount > 0) {
                $this->applyCardBalance(
                    cardId: $card->id,
                    direction: 'credit',
                    amount: $amount,
                    bookingId: $booking->id,
                    fundId: $fund->id,
                    expenseId: null,
                    description: "Top-up kartu fleet untuk booking #{$booking->kode_booking}"
                );
            }

            if ($driver->user_id) {
                $this->notifyUser($driver->user_id, 'fleet_card_fund_created', 'Kartu fleet baru', "{$booking->kode_booking} menunggu ACC kartu fleet {$card->code}.", [
                    'fund_id' => $fund->id,
                    'booking_id' => $booking->id,
                ], $fund);
            }

            return $fund->fresh($this->fundRelations);
        });
    }

    public function acceptFund(FleetCardFund $fund): FleetCardFund
    {
        return DB::transaction(function () use ($fund) {
            $fund = FleetCardFund::query()->lockForUpdate()->findOrFail($fund->id);

            if ($fund->status !== 'pending_driver_acceptance') {
                throw new \InvalidArgumentException('Kartu fleet ini sudah diproses.');
            }

            $fund->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => Auth::id(),
            ]);

            $this->notifyFinance($fund, 'fleet_card_fund_accepted', 'Kartu fleet diterima', "{$fund->booking?->kode_booking} sudah di-ACC driver.");

            return $fund->fresh($this->fundRelations);
        });
    }

    public function closeFund(FleetCardFund $fund, ?string $note = null): FleetCardFund
    {
        return DB::transaction(function () use ($fund, $note) {
            $fund = FleetCardFund::query()->lockForUpdate()->findOrFail($fund->id);

            if ($fund->status !== 'accepted') {
                throw new \InvalidArgumentException('Hanya kartu fleet yang sudah diterima driver yang bisa di-close.');
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

    public function createExpense(FleetCardFund $fund, array $data, mixed $photo = null): FleetCardExpense
    {
        return DB::transaction(function () use ($fund, $data, $photo) {
            $fund = FleetCardFund::query()->with('booking', 'driver', 'fleetCard')->lockForUpdate()->findOrFail($fund->id);

            if ($fund->status !== 'accepted') {
                throw new \InvalidArgumentException('Bon hanya bisa diinput setelah kartu diterima driver.');
            }

            $user = Auth::user();
            $isFinanceSource = in_array($user->role, ['superadmin', 'admin_branch', 'finance'], true);
            $photoPath = $photo ? $photo->store('fleet-card-receipts', 'public') : null;

            $expense = FleetCardExpense::create([
                'tenant_id' => $fund->tenant_id,
                'branch_id' => $fund->branch_id,
                'fleet_card_id' => $fund->fleet_card_id,
                'fleet_card_fund_id' => $fund->id,
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
                $this->applyExpenseDebit($expense, 'Bon kartu fleet diinput finance');
                $this->applyReturnCredit($expense, $fund);
            } else {
                $this->notifyFinance($fund, 'fleet_card_expense_submitted', 'Bon driver menunggu review', "{$fund->booking?->kode_booking} memiliki bon kartu fleet baru dari driver.");
            }

            return $expense->fresh(['costType', 'submitter', 'reviewer']);
        });
    }

    public function approveExpense(FleetCardExpense $expense): FleetCardExpense
    {
        return DB::transaction(function () use ($expense) {
            $expense = FleetCardExpense::query()
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

            $this->applyExpenseDebit($expense, 'Bon/pengembalian kartu fleet disetujui finance');
            $this->applyReturnCredit($expense, $expense->fund);

            if ($expense->driver?->user_id) {
                $this->notifyUser($expense->driver->user_id, 'fleet_card_expense_approved', 'Bon disetujui', 'Bon kartu fleet kamu sudah disetujui finance.', [
                    'expense_id' => $expense->id,
                    'fund_id' => $expense->fleet_card_fund_id,
                ], $expense->fund);
            }

            return $expense->fresh(['costType', 'submitter', 'reviewer']);
        });
    }

    public function rejectExpense(FleetCardExpense $expense, string $reason): FleetCardExpense
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
            $this->notifyUser($expense->driver->user_id, 'fleet_card_expense_rejected', 'Bon perlu diperbaiki', $reason, [
                'expense_id' => $expense->id,
                'fund_id' => $expense->fleet_card_fund_id,
            ], $expense->fund);
        }

        return $expense->fresh(['costType', 'submitter', 'reviewer']);
    }

    public function requestVoidFund(FleetCardFund $fund, string $reason): FleetCardFund
    {
        $this->assertCanUseBooking($fund->booking);

        if ($fund->status === 'void_requested') {
            throw new \InvalidArgumentException('Request void kartu fleet ini sedang menunggu approval supervisor.');
        }

        if ($fund->status === 'cancelled') {
            throw new \InvalidArgumentException('Kartu fleet fund ini sudah dibatalkan/void.');
        }

        $fund->update([
            'status_before_void' => $fund->status,
            'status' => 'void_requested',
            'void_reason' => $reason,
            'void_requested_by' => Auth::id(),
            'void_requested_at' => now(),
            'void_approved_by' => null,
            'void_approved_at' => null,
            'void_rejected_by' => null,
            'void_rejected_at' => null,
            'void_rejection_note' => null,
        ]);

        return $fund->fresh($this->fundRelations);
    }

    public function approveVoidFund(FleetCardFund $fund): FleetCardFund
    {
        $this->assertCanUseBooking($fund->booking);

        if ($fund->status !== 'void_requested') {
            throw new \InvalidArgumentException('Hanya request void berstatus pending yang bisa disetujui.');
        }

        $reason = $fund->void_reason;
        $statusBeforeVoid = $fund->status_before_void;

        return DB::transaction(function () use ($fund, $reason, $statusBeforeVoid) {
            if ((int) $fund->amount > 0 && in_array($statusBeforeVoid, ['accepted', 'closed'])) {
                $this->applyCardBalance(
                    cardId: $fund->fleet_card_id,
                    direction: 'debit',
                    amount: (int) $fund->amount,
                    bookingId: $fund->booking_id,
                    fundId: $fund->id,
                    expenseId: null,
                    description: 'Void top-up kartu fleet: ' . $reason
                );
            }

            $fund->update([
                'status' => 'cancelled',
                'cancelled_by' => Auth::id(),
                'close_note' => $fund->close_note ? $fund->close_note . ' | Void: ' . $reason : 'Void: ' . $reason,
                'void_approved_by' => Auth::id(),
                'void_approved_at' => now(),
            ]);

            if ((int) $fund->amount > 0 && $fund->payment_account_id) {
                $account = PaymentAccount::lockForUpdate()->findOrFail($fund->payment_account_id);
                $this->transactionService->applyDelta($account, (int) $fund->amount, [
                    'type' => 'fleet_card_topup_void',
                    'amount' => (int) $fund->amount,
                    'description' => "Void top-up kartu fleet {$fund->fleetCard?->code}: " . $reason,
                    'created_by' => Auth::id(),
                    'transaction_at' => now(),
                    'status' => 'voided',
                    'void_requested_by' => $fund->void_requested_by,
                    'void_requested_at' => $fund->void_requested_at,
                    'void_approved_by' => $fund->void_approved_by,
                    'void_approved_at' => $fund->void_approved_at,
                ]);
            }

            return $fund->fresh($this->fundRelations);
        });
    }

    public function rejectVoidFund(FleetCardFund $fund, ?string $note = null): FleetCardFund
    {
        $this->assertCanUseBooking($fund->booking);

        if ($fund->status !== 'void_requested') {
            throw new \InvalidArgumentException('Hanya request void berstatus pending yang bisa ditolak.');
        }

        $fund->update([
            'status' => $fund->status_before_void ?: 'accepted',
            'status_before_void' => null,
            'void_rejected_by' => Auth::id(),
            'void_rejected_at' => now(),
            'void_rejection_note' => $note,
        ]);

        return $fund->fresh($this->fundRelations);
    }

    public function requestVoidExpense(FleetCardExpense $expense, string $reason): FleetCardExpense
    {
        $this->assertCanUseBooking($expense->booking);

        if ($expense->status === 'void_requested') {
            throw new \InvalidArgumentException('Request void bon ini sedang menunggu approval supervisor.');
        }

        if ($expense->status !== 'approved') {
            throw new \InvalidArgumentException('Hanya bon berstatus approved yang bisa divoid.');
        }

        $expense->update([
            'status' => 'void_requested',
            'void_reason' => $reason,
            'void_requested_by' => Auth::id(),
            'void_requested_at' => now(),
            'void_approved_by' => null,
            'void_approved_at' => null,
            'void_rejected_by' => null,
            'void_rejected_at' => null,
            'void_rejection_note' => null,
        ]);

        return $expense->fresh(['costType', 'submitter', 'reviewer']);
    }

    public function approveVoidExpense(FleetCardExpense $expense): FleetCardExpense
    {
        $this->assertCanUseBooking($expense->booking);

        if ($expense->status !== 'void_requested') {
            throw new \InvalidArgumentException('Hanya request void berstatus pending yang bisa disetujui.');
        }

        return DB::transaction(function () use ($expense) {
            $this->applyCardBalance(
                cardId: $expense->fleet_card_id,
                direction: 'credit',
                amount: (int) $expense->amount,
                bookingId: $expense->booking_id,
                fundId: $expense->fleet_card_fund_id,
                expenseId: $expense->id,
                description: 'Void bon kartu fleet: ' . $expense->void_reason
            );

            $expense->update([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => '[VOID] ' . $expense->void_reason,
                'void_approved_by' => Auth::id(),
                'void_approved_at' => now(),
            ]);

            return $expense->fresh(['costType', 'submitter', 'reviewer']);
        });
    }

    public function rejectVoidExpense(FleetCardExpense $expense, ?string $note = null): FleetCardExpense
    {
        $this->assertCanUseBooking($expense->booking);

        if ($expense->status !== 'void_requested') {
            throw new \InvalidArgumentException('Hanya request void berstatus pending yang bisa ditolak.');
        }

        $expense->update([
            'status' => 'approved',
            'void_rejected_by' => Auth::id(),
            'void_rejected_at' => now(),
            'void_rejection_note' => $note,
        ]);

        return $expense->fresh(['costType', 'submitter', 'reviewer']);
    }

    public function history(array $filters = []): Paginator
    {
        $user = Auth::user();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        $funds = FleetCardFund::query()
            ->with(['fleetCard', 'booking.customer', 'driver', 'paymentAccount', 'creator'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['fleet_card_id'] ?? null, fn ($q, $cardId) => $q->where('fleet_card_id', $cardId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('recipient_destination', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn ($booking) => $booking->where('kode_booking', 'like', "%{$search}%"))
                        ->orWhereHas('fleetCard', fn ($card) => $card->where('code', 'like', "%{$search}%"))
                        ->orWhereHas('driver', fn ($driver) => $driver->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->get()
            ->map(fn ($fund) => [
                'id' => 'fund-' . $fund->id,
                'type' => 'transfer',
                'label' => 'Top-up Kartu Fleet ' . $fund->fleetCard?->code,
                'booking_id' => $fund->booking_id,
                'booking_code' => $fund->booking?->kode_booking,
                'customer_name' => $fund->booking?->customer?->nama,
                'driver_name' => $fund->driver?->nama,
                'fleet_card_code' => $fund->fleetCard?->code,
                'amount' => (int) $fund->amount,
                'direction' => 'out',
                'status' => $fund->status,
                'happened_at' => $fund->paid_at?->toISOString() ?? $fund->created_at?->toISOString(),
                'description' => $fund->recipient_destination,
                'created_by_name' => $fund->creator?->name,
            ]);

        $expenses = FleetCardExpense::query()
            ->with(['booking.customer', 'driver', 'costType', 'fleetCard', 'fund.paymentAccount', 'submitter', 'reviewer'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($filters['fleet_card_id'] ?? null, fn ($q, $cardId) => $q->where('fleet_card_id', $cardId))
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
                'label' => $expense->type === 'return' ? 'Pengembalian Sisa Kartu' : 'Bon Kartu Fleet',
                'booking_id' => $expense->booking_id,
                'booking_code' => $expense->booking?->kode_booking,
                'customer_name' => $expense->booking?->customer?->nama,
                'driver_name' => $expense->driver?->nama,
                'fleet_card_code' => $expense->fleetCard?->code,
                'amount' => (int) $expense->amount,
                'direction' => $expense->type === 'return' ? 'in' : 'out',
                'status' => $expense->status,
                'happened_at' => $expense->reviewed_at?->toISOString() ?? $expense->created_at?->toISOString(),
                'description' => $expense->description,
                'created_by_name' => $expense->submitter?->name,
                'reviewed_by_name' => $expense->reviewer?->name,
                'cost_type_name' => $expense->costType?->nama,
                'rejection_reason' => $expense->rejection_reason,
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

    public function driverFunds(array $filters = []): LengthAwarePaginator
    {
        $driver = $this->currentDriver();

        return FleetCardFund::query()
            ->with($this->fundRelations)
            ->where('driver_id', $driver->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
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

    private function applyExpenseDebit(FleetCardExpense $expense, string $description): void
    {
        $this->applyCardBalance(
            cardId: $expense->fleet_card_id,
            direction: 'debit',
            amount: (int) $expense->amount,
            bookingId: $expense->booking_id,
            fundId: $expense->fleet_card_fund_id,
            expenseId: $expense->id,
            description: $description
        );
    }

    private function applyReturnCredit(FleetCardExpense $expense, ?FleetCardFund $fund = null): void
    {
        if ($expense->type !== 'return') {
            return;
        }

        $accountId = $fund?->payment_account_id ?? $expense->payment_account_id;
        if (! $accountId) {
            return;
        }

        $account = PaymentAccount::lockForUpdate()->findOrFail($accountId);
        $this->transactionService->applyDelta($account, (int) $expense->amount, [
            'type' => 'fleet_card_return_in',
            'amount' => (int) $expense->amount,
            'description' => "Pengembalian sisa kartu fleet oleh driver " . ($expense->driver?->nama ?? "Driver") . " untuk booking #{$expense->booking?->kode_booking}",
            'created_by' => Auth::id(),
            'transaction_at' => now(),
        ]);
    }

    private function applyCardBalance(
        int $cardId,
        string $direction,
        int $amount,
        ?int $bookingId,
        ?int $fundId,
        ?int $expenseId,
        string $description
    ): void {
        $card = FleetCard::query()->lockForUpdate()->findOrFail($cardId);
        $before = (int) $card->saldo;
        $after = $direction === 'credit' ? $before + $amount : $before - $amount;

        if ($after < 0) {
            throw new \InvalidArgumentException('Saldo kartu fleet tidak cukup untuk transaksi ini.');
        }

        $card->update(['saldo' => $after]);

        FleetCardBalanceLedger::create([
            'tenant_id' => $card->tenant_id,
            'branch_id' => $card->branch_id,
            'fleet_card_id' => $card->id,
            'booking_id' => $bookingId,
            'fleet_card_fund_id' => $fundId,
            'fleet_card_expense_id' => $expenseId,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
            'created_by' => Auth::id(),
        ]);
    }

    private function attachDetailSummary(BookingDetail $detail): BookingDetail
    {
        $funds = $detail->fleetCardFunds;
        $expenses = $detail->fleetCardExpenses;

        $topupTotal = $funds->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed'])->sum(fn ($fund) => (int) $fund->amount);
        $approvedExpenseTotal = $expenses->whereIn('status', ['approved', 'void_requested'])->where('type', 'expense')->sum(fn ($e) => (int) $e->amount);
        $approvedReturnTotal = $expenses->whereIn('status', ['approved', 'void_requested'])->where('type', 'return')->sum(fn ($e) => (int) $e->amount);

        $detail->fleet_card_topup_total = $topupTotal;
        $detail->fleet_card_approved_expense_total = $approvedExpenseTotal;
        $detail->fleet_card_approved_return_total = $approvedReturnTotal;
        $detail->fleet_card_active_fund_count = $funds->whereIn('status', ['pending_driver_acceptance', 'accepted'])->count();
        $detail->fleet_card_pending_review_count = $expenses->where('status', 'submitted')->count();

        return $detail;
    }

    private function notifyFinance(FleetCardFund $fund, string $type, string $title, string $message): void
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

    private function notifyUser(int $userId, string $type, string $title, ?string $message, array $data, FleetCardFund $fund): void
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
