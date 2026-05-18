<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\RentalOwner;
use App\Models\RentToRentBill;
use App\Models\RentToRentDebt;
use App\Models\RentToRentPayment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentToRentService
{
    public function listDebts(array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = RentToRentDebt::query()
            ->with($this->debtListRelations())
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['rental_owner_id'] ?? null, fn($query, $ownerId) => $query->where('rental_owner_id', $ownerId))
            ->where('status', '!=', 'cancelled')
            ->whereHas('booking', fn($query) => $query->whereNotIn('status', ['batal', 'follow_up', 'confirm']))
            ->whereHas('bookingDetail', fn($query) => $query->where('status', '!=', 'batal'));

        if (!empty($filters['search'])) {
            $needle = trim(strtolower($filters['search']));
            $query->where(function ($q) use ($needle) {
                $q->whereHas('booking', fn($sq) => $sq->where('kode_booking', 'like', "%{$needle}%")->orWhere('tujuan', 'like', "%{$needle}%")->orWhereHas('customer', fn($cq) => $cq->where('nama', 'like', "%{$needle}%")))
                  ->orWhereHas('bookingDetail.unit', fn($sq) => $sq->where('merk', 'like', "%{$needle}%")->orWhere('tipe', 'like', "%{$needle}%")->orWhere('no_polisi', 'like', "%{$needle}%"))
                  ->orWhereHas('rentalOwner', fn($sq) => $sq->where('nama', 'like', "%{$needle}%"));
            });
        }

        if (!empty($filters['status'])) {
            $query->where('cached_payment_status', $filters['status']);
        }

        $summaryQuery = clone $query;
        $aggregate = $summaryQuery
            ->toBase()
            ->selectRaw('
                COALESCE(SUM(cached_total_amount), 0) as total_amount,
                COALESCE(SUM(cached_paid_amount), 0) as paid_amount,
                COUNT(*) as debt_count,
                COUNT(DISTINCT rental_owner_id) as owner_count
            ')
            ->first();

        $totalAmount = (int) ($aggregate->total_amount ?? 0);
        $paidAmount = (int) ($aggregate->paid_amount ?? 0);

        $summary = [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, $totalAmount - $paidAmount),
            'debt_count' => (int) ($aggregate->debt_count ?? 0),
            'owner_count' => (int) ($aggregate->owner_count ?? 0),
        ];

        $debts = $query->latest()->paginate($perPage)->withQueryString();

        return [
            'debts' => $debts,
            'summary' => $summary,
            'owner_options' => $this->debtOwnerOptions($filters),
        ];
    }

    public function bills(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return RentToRentBill::query()
            ->with($this->billRelations())
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['rental_owner_id'] ?? null, fn($query, $ownerId) => $query->where('rental_owner_id', $ownerId))
            ->when($filters['status'] ?? null, function ($query, $status) {
                $status === 'paid'
                    ? $query->whereIn('status', ['paid', 'paid_manual'])
                    : $query->where('status', $status);
            })
            ->latest('generated_at')
            ->paginate($perPage);
    }

    public function debtOwnerOptions(array $filters = []): Collection
    {
        $ownerIds = RentToRentDebt::query()
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->where('status', '!=', 'cancelled')
            ->whereHas('booking', fn($query) => $query->whereNotIn('status', ['batal', 'follow_up', 'confirm']))
            ->whereHas('bookingDetail', fn($query) => $query->where('status', '!=', 'batal'))
            ->distinct()
            ->pluck('rental_owner_id')
            ->filter()
            ->values();

        return RentalOwner::query()
            ->select(['id', 'nama'])
            ->whereIn('id', $ownerIds)
            ->orderBy('nama')
            ->get();
    }

    public function showDebt(RentToRentDebt $debt): RentToRentDebt
    {
        return $debt->load($this->debtDetailRelations());
    }

    public function updateDebtAmount(RentToRentDebt $debt, ?int $amount): RentToRentDebt
    {
        $debt->loadMissing('billItems.bill');

        if ($this->hasLockedBillItem($debt)) {
            throw new \InvalidArgumentException('Nominal rent-to-rent tidak bisa diedit karena sudah masuk dokumen tagihan.');
        }

        $debt->update([
            'amount_override' => $amount,
        ]);

        $this->refreshDebtCache($debt);

        return $debt->fresh($this->debtDetailRelations());
    }

    public function createBill(array $debtIds, int $branchId, int $tenantId): RentToRentBill
    {
        return DB::transaction(function () use ($debtIds, $branchId, $tenantId) {
            $uniqueDebtIds = array_values(array_unique($debtIds));
            $debts = RentToRentDebt::query()
                ->with($this->debtDetailRelations())
                ->whereIn('id', $uniqueDebtIds)
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->get();

            if ($debts->count() !== count($uniqueDebtIds)) {
                throw new \InvalidArgumentException('Sebagian transaksi rent-to-rent tidak ditemukan pada branch aktif.');
            }

            $ownerIds = $debts->pluck('rental_owner_id')->unique();
            if ($ownerIds->count() !== 1) {
                throw new \InvalidArgumentException('Dokumen rent-to-rent hanya bisa berisi transaksi dari satu pemilik rental.');
            }

            $lockedDebt = $debts->first(fn(RentToRentDebt $debt) => $this->hasLockedBillItem($debt));
            if ($lockedDebt) {
                throw new \InvalidArgumentException("Booking {$lockedDebt->booking?->kode_booking} sudah masuk dokumen tagihan aktif.");
            }

            $paidDebt = $debts->first(fn(RentToRentDebt $debt) => $this->paidAmountForDebt($debt) > 0);
            if ($paidDebt) {
                throw new \InvalidArgumentException("Booking {$paidDebt->booking?->kode_booking} sudah memiliki pembayaran langsung dan tidak bisa dibuatkan dokumen tagihan baru.");
            }

            $invalidDebt = $debts->first(fn(RentToRentDebt $debt) =>
                $debt->status === 'cancelled'
                || in_array($debt->booking?->status, ['batal', 'follow_up', 'confirm'], true)
                || $debt->bookingDetail?->status === 'batal'
                || $this->currentAmount($debt) <= 0
            );
            if ($invalidDebt) {
                throw new \InvalidArgumentException("Booking {$invalidDebt->booking?->kode_booking} tidak memiliki nominal rent-to-rent aktif.");
            }

            $amounts = $debts->mapWithKeys(fn(RentToRentDebt $debt) => [
                $debt->id => $this->currentAmount($debt),
            ]);

            $bill = RentToRentBill::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'rental_owner_id' => (int) $ownerIds->first(),
                'bill_number' => $this->nextBillNumber($branchId),
                'public_token' => $this->newPublicToken(),
                'status' => 'generated',
                'total_amount' => (int) $amounts->sum(),
                'paid_amount' => 0,
                'generated_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($debts as $debt) {
                $bill->items()->create([
                    'rent_to_rent_debt_id' => $debt->id,
                    'booking_detail_id' => $debt->booking_detail_id,
                    'amount' => (int) $amounts[$debt->id],
                ]);

                $debt->update(['status' => 'billed']);
                $debt->unsetRelation('billItems');
                $this->refreshDebtCache($debt);
            }

            return $bill->fresh($this->billRelations());
        });
    }

    public function storeDebtPayment(RentToRentDebt $debt, array $data): RentToRentDebt
    {
        return DB::transaction(function () use ($debt, $data) {
            $debt = RentToRentDebt::query()
                ->with($this->debtDetailRelations())
                ->lockForUpdate()
                ->findOrFail($debt->id);

            if ($debt->status === 'cancelled') {
                throw new \InvalidArgumentException('Transaksi rent-to-rent batal tidak bisa dibayar.');
            }

            if (in_array($debt->booking?->status, ['batal', 'follow_up', 'confirm'], true) || $debt->bookingDetail?->status === 'batal') {
                throw new \InvalidArgumentException('Transaksi rent-to-rent belum aktif atau sudah batal.');
            }

            if ($this->hasLockedBillItem($debt)) {
                throw new \InvalidArgumentException('Transaksi sudah masuk dokumen tagihan aktif. Catat pembayaran dari dokumen tagihan.');
            }

            $remaining = $this->remainingAmountForDebt($debt);
            if ((int) $data['amount'] > $remaining) {
                throw new \InvalidArgumentException('Nominal pembayaran melebihi sisa hutang rent-to-rent.');
            }

            $payment = RentToRentPayment::create([
                'rent_to_rent_bill_id' => null,
                'payment_account_id' => $data['payment_account_id'],
                'amount' => (int) $data['amount'],
                'status' => 'active',
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'created_by' => auth()->id(),
            ]);

            $payment->allocations()->create([
                'rent_to_rent_bill_item_id' => null,
                'rent_to_rent_debt_id' => $debt->id,
                'amount' => (int) $payment->amount,
            ]);

            $debt = $debt->fresh($this->debtDetailRelations());
            $debt->update(['status' => $this->paymentStatusForDebt($debt)]);
            $this->refreshDebtCache($debt->fresh($this->debtDetailRelations()));

            return $debt->fresh($this->debtDetailRelations());
        });
    }

    public function markSent(RentToRentBill $bill): RentToRentBill
    {
        $bill->update([
            'status' => $bill->status === 'generated' ? 'sent' : $bill->status,
            'sent_at' => now(),
        ]);

        return $bill->fresh($this->billRelations());
    }

    public function markDebtPaid(RentToRentDebt $debt): RentToRentDebt
    {
        return DB::transaction(function () use ($debt) {
            $debt = RentToRentDebt::query()
                ->with($this->debtDetailRelations())
                ->lockForUpdate()
                ->findOrFail($debt->id);

            if ($debt->status === 'cancelled') {
                throw new \InvalidArgumentException('Transaksi rent-to-rent batal tidak bisa ditandai paid.');
            }

            if ($this->hasLockedBillItem($debt)) {
                throw new \InvalidArgumentException('Transaksi sudah masuk dokumen tagihan aktif. Tandai paid dari dokumen tagihan.');
            }

            $debt->update(['status' => 'paid_manual']);
            $this->refreshDebtCache($debt->fresh($this->debtDetailRelations()));

            return $debt->fresh($this->debtDetailRelations());
        });
    }

    public function markBillPaid(RentToRentBill $bill): RentToRentBill
    {
        return DB::transaction(function () use ($bill) {
            $bill = RentToRentBill::query()
                ->with($this->billRelations())
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if (in_array($bill->status, ['void', 'void_requested'], true)) {
                throw new \InvalidArgumentException('Dokumen void atau menunggu ACC void tidak bisa ditandai paid.');
            }

            $bill->update(['status' => 'paid_manual']);

            foreach ($bill->items as $item) {
                if (! $item->debt) {
                    continue;
                }

                $item->debt->update(['status' => 'paid_manual']);
                $this->refreshDebtCache($item->debt->fresh($this->debtDetailRelations()));
            }

            return $bill->fresh($this->billRelations());
        });
    }

    public function storePayment(RentToRentBill $bill, array $data): RentToRentBill
    {
        return DB::transaction(function () use ($bill, $data) {
            $bill = RentToRentBill::query()
                ->with($this->billRelations())
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if ($bill->status === 'void') {
                throw new \InvalidArgumentException('Dokumen void tidak bisa dibayar.');
            }

            if ($bill->status === 'void_requested') {
                throw new \InvalidArgumentException('Dokumen sedang menunggu ACC void.');
            }

            $remaining = max(0, (int) $bill->total_amount - $this->billPaidAmount($bill));
            if ((int) $data['amount'] > $remaining) {
                throw new \InvalidArgumentException('Nominal pembayaran melebihi sisa tagihan rent-to-rent.');
            }

            $payment = RentToRentPayment::create([
                'rent_to_rent_bill_id' => $bill->id,
                'payment_account_id' => $data['payment_account_id'],
                'amount' => (int) $data['amount'],
                'status' => 'active',
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'created_by' => auth()->id(),
            ]);

            $remainingPayment = (int) $payment->amount;

            foreach ($bill->items as $item) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $alreadyAllocated = (int) $item->allocations
                    ->filter(fn($allocation) => ($allocation->payment?->status ?? 'active') !== 'voided')
                    ->sum('amount');
                $itemRemaining = max(0, (int) $item->amount - $alreadyAllocated);
                $allocated = min($remainingPayment, $itemRemaining);

                if ($allocated <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'rent_to_rent_bill_item_id' => $item->id,
                    'rent_to_rent_debt_id' => $item->rent_to_rent_debt_id,
                    'amount' => $allocated,
                ]);

                $remainingPayment -= $allocated;
            }

            $bill = $bill->fresh($this->billRelations());
            $paidAmount = $this->billPaidAmount($bill);
            $bill->update([
                'paid_amount' => $paidAmount,
                'status' => $this->billStatus((int) $bill->total_amount, $paidAmount, $bill->sent_at !== null),
            ]);

            foreach ($bill->items as $item) {
                $itemPaid = (int) $item->allocations
                    ->filter(fn($allocation) => ($allocation->payment?->status ?? 'active') !== 'voided')
                    ->sum('amount');
                $item->debt?->update([
                    'status' => $itemPaid >= (int) $item->amount ? 'paid' : ($itemPaid > 0 ? 'partial_paid' : 'billed'),
                ]);
                if ($item->debt) {
                    $this->refreshDebtCache($item->debt);
                }
            }

            return $bill->fresh($this->billRelations());
        });
    }

    public function requestVoidPayment(RentToRentPayment $payment, string $reason): array
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment = RentToRentPayment::query()
                ->with(['bill.items.debt', 'allocations.debt'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (($payment->status ?? 'active') === 'voided') {
                throw new \InvalidArgumentException('Pembayaran ini sudah void.');
            }

            if (($payment->status ?? 'active') === 'void_requested') {
                throw new \InvalidArgumentException('Request void pembayaran ini masih menunggu ACC supervisor.');
            }

            if (in_array($payment->bill?->status, ['void', 'void_requested'], true)) {
                throw new \InvalidArgumentException('Pembayaran pada dokumen void atau menunggu ACC void tidak bisa di-void terpisah.');
            }

            $payment->update([
                'status' => 'void_requested',
                'void_reason' => $reason,
                'void_requested_by' => auth()->id(),
                'void_requested_at' => now(),
                'void_approved_by' => null,
                'void_approved_at' => null,
                'void_rejected_by' => null,
                'void_rejected_at' => null,
                'void_rejection_note' => null,
            ]);

            return $this->formatPaymentHistory($payment->fresh([
                'bill.rentalOwner',
                'bill.items.debt.booking.customer',
                'allocations.debt.rentalOwner',
                'allocations.debt.booking.customer',
                'paymentAccount',
                'creator',
            ]));
        });
    }

    public function approveVoidPayment(RentToRentPayment $payment): array
    {
        $user = auth()->user();
        if (! in_array($user?->role, ['superadmin', 'supervisor'], true)) {
            throw new \InvalidArgumentException('Void pembayaran hanya bisa di-ACC oleh supervisor.');
        }

        if ($payment->void_requested_by && $payment->void_requested_by === $user?->id && $user?->role !== 'superadmin') {
            throw new \InvalidArgumentException('Request void harus di-ACC oleh user berbeda.');
        }

        return DB::transaction(function () use ($payment) {
            $payment = RentToRentPayment::query()
                ->with(['bill.items.debt', 'allocations.debt'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (($payment->status ?? 'active') !== 'void_requested') {
                throw new \InvalidArgumentException('Pembayaran belum dalam status menunggu ACC void.');
            }

            $payment->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_approved_by' => auth()->id(),
                'void_approved_at' => now(),
            ]);

            $this->syncAfterPaymentVoid($payment->fresh(['bill.items.debt', 'allocations.debt']));

            return $this->formatPaymentHistory($payment->fresh([
                'bill.rentalOwner',
                'bill.items.debt.booking.customer',
                'allocations.debt.rentalOwner',
                'allocations.debt.booking.customer',
                'paymentAccount',
                'creator',
            ]));
        });
    }

    public function rejectVoidPayment(RentToRentPayment $payment, ?string $note = null): array
    {
        return DB::transaction(function () use ($payment, $note) {
            $payment = RentToRentPayment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if (($payment->status ?? 'active') !== 'void_requested') {
                throw new \InvalidArgumentException('Pembayaran belum dalam status menunggu ACC void.');
            }

            $payment->update([
                'status' => 'active',
                'void_rejected_by' => auth()->id(),
                'void_rejected_at' => now(),
                'void_rejection_note' => $note,
            ]);

            return $this->formatPaymentHistory($payment->fresh([
                'bill.rentalOwner',
                'bill.items.debt.booking.customer',
                'allocations.debt.rentalOwner',
                'allocations.debt.booking.customer',
                'paymentAccount',
                'creator',
            ]));
        });
    }

    public function paymentHistory(array $filters = []): array
    {
        $view = $filters['view'] ?? 'all';
        $payments = collect();
        $groups = collect();
        $latestMeta = $this->emptyHistoryPaginatorMeta((int) ($filters['latest_per_page'] ?? $filters['latest_limit'] ?? 20));
        $groupMeta = $this->emptyHistoryPaginatorMeta((int) ($filters['group_per_page'] ?? $filters['group_limit'] ?? 10));

        if (in_array($view, ['all', 'latest'], true)) {
            $latestPaginator = $this->latestPaymentHistory($filters);
            $payments = $latestPaginator->getCollection();
            $latestMeta = $this->historyPaginatorMeta($latestPaginator);
        }

        if (in_array($view, ['all', 'group'], true)) {
            $groupPaginator = $this->groupedPaymentHistory($filters);
            $groups = $groupPaginator->getCollection();
            $groupMeta = $this->historyPaginatorMeta($groupPaginator);
        }

        return [
            'latest' => $payments,
            'groups' => $groups,
            'meta' => [
                'latest' => $latestMeta,
                'groups' => $groupMeta,
            ],
        ];
    }

    private function latestPaymentHistory(array $filters): LengthAwarePaginator
    {
        $perPage = $this->historyPerPage($filters['latest_per_page'] ?? $filters['latest_limit'] ?? 20);
        $page = $this->historyPage($filters['latest_page'] ?? 1);

        $paginator = RentToRentPayment::query()
            ->with([
                'bill.rentalOwner',
                'bill.items.debt.booking.customer',
                'allocations.debt.rentalOwner',
                'allocations.debt.booking.customer',
                'paymentAccount',
                'creator',
            ])
            ->when($filters['tenant_id'] ?? null, function ($query, $tenantId) {
                $query->where(function ($scope) use ($tenantId) {
                    $scope->whereHas('bill', fn($bill) => $bill->where('tenant_id', $tenantId))
                        ->orWhereHas('allocations.debt', fn($debt) => $debt->where('tenant_id', $tenantId));
                });
            })
            ->when($filters['branch_id'] ?? null, function ($query, $branchId) {
                $query->where(function ($scope) use ($branchId) {
                    $scope->whereHas('bill', fn($bill) => $bill->where('branch_id', $branchId))
                        ->orWhereHas('allocations.debt', fn($debt) => $debt->where('branch_id', $branchId));
                });
            })
            ->latest('paid_at')
            ->paginate($perPage, ['*'], 'latest_page', $page);

        $paginator->setCollection($paginator->getCollection()
            ->map(fn(RentToRentPayment $payment) => $this->formatPaymentHistory($payment))
            ->values());

        return $paginator;
    }

    private function groupedPaymentHistory(array $filters): LengthAwarePaginator
    {
        $perPage = $this->historyPerPage($filters['group_per_page'] ?? $filters['group_limit'] ?? 10);
        $page = $this->historyPage($filters['group_page'] ?? 1);

        $paginator = RentToRentBill::query()
            ->with(['rentalOwner', 'payments.paymentAccount', 'payments.creator', 'items.debt.booking.customer'])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->whereHas('payments')
            ->latest('generated_at')
            ->paginate($perPage, ['*'], 'group_page', $page);

        $paginator->setCollection($paginator->getCollection()
            ->map(function (RentToRentBill $bill) {
                $payments = $bill->payments
                    ->sortByDesc(fn($payment) => $payment->paid_at?->timestamp ?? 0)
                    ->values();

                return [
                    'bill_id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'owner_name' => $bill->rentalOwner?->nama,
                    'booking_codes' => $bill->items->pluck('debt.booking.kode_booking')->filter()->unique()->values(),
                    'payment_count' => $payments->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided')->count(),
                    'latest_paid_at' => $payments->max(fn($payment) => $payment->paid_at?->toISOString()),
                    'total_amount' => (int) $payments
                        ->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided')
                        ->sum('amount'),
                    'payments' => $payments->map(fn(RentToRentPayment $payment) => $this->formatPaymentHistory($payment))->values(),
                ];
            })
            ->values());

        return $paginator;
    }

    private function historyPerPage(int $perPage): int
    {
        return max(5, min(100, $perPage));
    }

    private function historyPage(int $page): int
    {
        return max(1, $page);
    }

    private function historyPaginatorMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function emptyHistoryPaginatorMeta(int $perPage): array
    {
        return [
            'total' => 0,
            'per_page' => $this->historyPerPage($perPage),
            'current_page' => 1,
            'last_page' => 1,
        ];
    }

    public function showBill(RentToRentBill $bill): RentToRentBill
    {
        return $bill->load($this->billRelations());
    }

    public function publicBill(string $token): RentToRentBill
    {
        return RentToRentBill::query()
            ->with($this->billRelations())
            ->where('public_token', $token)
            ->where('status', '!=', 'void')
            ->firstOrFail();
    }

    public function requestVoid(RentToRentBill $bill, string $reason): RentToRentBill
    {
        if (in_array($bill->status, ['void', 'void_requested'], true)) {
            throw new \InvalidArgumentException('Dokumen ini sudah void atau sedang menunggu ACC void.');
        }

        $bill->update([
            'status' => 'void_requested',
            'void_reason' => $reason,
            'void_requested_by' => auth()->id(),
            'void_requested_at' => now(),
            'void_approved_by' => null,
            'void_approved_at' => null,
            'void_rejected_by' => null,
            'void_rejected_at' => null,
            'void_rejection_note' => null,
        ]);

        return $bill->fresh($this->billRelations());
    }

    public function approveVoid(RentToRentBill $bill): RentToRentBill
    {
        $user = auth()->user();
        if (! in_array($user?->role, ['superadmin', 'supervisor'], true)) {
            throw new \InvalidArgumentException('Void tagihan hanya bisa di-ACC oleh supervisor.');
        }

        if ($bill->void_requested_by && $bill->void_requested_by === $user?->id && $user?->role !== 'superadmin') {
            throw new \InvalidArgumentException('Request void harus di-ACC oleh user berbeda.');
        }

        return DB::transaction(function () use ($bill) {
            $bill = RentToRentBill::query()
                ->with($this->billRelations())
                ->lockForUpdate()
                ->findOrFail($bill->id);

            if ($bill->status !== 'void_requested') {
                throw new \InvalidArgumentException('Dokumen belum dalam status menunggu ACC void.');
            }

            foreach ($bill->payments as $payment) {
                $payment->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                ]);
            }

            $bill->update([
                'status' => 'void',
                'paid_amount' => 0,
                'voided_at' => now(),
                'void_approved_by' => auth()->id(),
                'void_approved_at' => now(),
            ]);

            foreach ($bill->items as $item) {
                $item->debt?->update(['status' => 'open']);
                if ($item->debt) {
                    $item->debt->unsetRelation('billItems');
                    $this->refreshDebtCache($item->debt);
                }
            }

            return $bill->fresh($this->billRelations());
        });
    }

    public function rejectVoid(RentToRentBill $bill, ?string $note = null): RentToRentBill
    {
        if ($bill->status !== 'void_requested') {
            throw new \InvalidArgumentException('Dokumen belum dalam status menunggu ACC void.');
        }

        $paidAmount = $this->billPaidAmount($bill);
        $bill->update([
            'status' => $this->billStatus((int) $bill->total_amount, $paidAmount, $bill->sent_at !== null),
            'paid_amount' => $paidAmount,
            'void_rejected_by' => auth()->id(),
            'void_rejected_at' => now(),
            'void_rejection_note' => $note,
        ]);

        return $bill->fresh($this->billRelations());
    }

    public function syncDetail(BookingDetail $detail): ?RentToRentDebt
    {
        $detail->loadMissing(['booking', 'unit.rentalOwner']);
        $booking = $detail->booking;
        $unit = $detail->unit;
        $owner = $unit?->rentalOwner;
        $existing = RentToRentDebt::where('booking_detail_id', $detail->id)->first();

        if (! $booking || ! $unit || ! $owner || $owner->is_owner || in_array($booking->status, ['batal', 'follow_up', 'confirm'], true) || $detail->status === 'batal') {
            if ($existing && ! $this->hasLockedBillItem($existing->loadMissing('billItems.bill'))) {
                $existing->update(['status' => 'cancelled']);
                $this->refreshDebtCache($existing);
            }

            return null;
        }

        if ($existing && $this->hasLockedBillItem($existing->loadMissing('billItems.bill'))) {
            $this->refreshDebtCache($existing);
            return $existing;
        }

        $debt = RentToRentDebt::updateOrCreate(
            ['booking_detail_id' => $detail->id],
            [
                'tenant_id' => $booking->tenant_id,
                'branch_id' => $booking->branch_id,
                'rental_owner_id' => $owner->id,
                'booking_id' => $booking->id,
                'status' => 'open',
            ]
        );

        $this->refreshDebtCache($debt);

        return $debt;
    }

    public function currentAmount(RentToRentDebt $debt): int
    {
        if ($debt->amount_override !== null) {
            return (int) $debt->amount_override;
        }

        $detail = $debt->relationLoaded('bookingDetail')
            ? $debt->bookingDetail
            : $debt->bookingDetail()->with('unit')->first();

        $unit = $detail?->unit;
        $duration = (int) ($detail?->lama_sewa ?? 1);
        $package = $detail?->paket_sewa ?? 'harian';

        $base = match ($package) {
            'mingguan' => (int) ($unit?->modal_1_minggu ?? 0),
            'bulanan' => (int) ($unit?->modal_1_bulan ?? 0),
            default => (int) ($unit?->modal_1_hari ?? 0),
        };

        return $base * max(1, $duration);
    }

    public function displayAmount(RentToRentDebt $debt): int
    {
        $item = $this->activeBillItem($debt);

        return $item ? (int) $item->amount : $this->currentAmount($debt);
    }

    public function paidAmountForDebt(RentToRentDebt $debt): int
    {
        if (! $debt->relationLoaded('paymentAllocations')) {
            $debt->load('paymentAllocations.payment');
        }

        return (int) $debt->paymentAllocations
            ->filter(fn($allocation) => ($allocation->payment?->status ?? 'active') !== 'voided')
            ->sum('amount');
    }

    public function remainingAmountForDebt(RentToRentDebt $debt): int
    {
        return max(0, $this->displayAmount($debt) - $this->paidAmountForDebt($debt));
    }

    public function paymentStatusForDebt(RentToRentDebt $debt): string
    {
        if ($debt->status === 'cancelled') {
            return 'cancelled';
        }

        if ($debt->status === 'paid_manual') {
            return 'paid';
        }

        $amount = $this->displayAmount($debt);
        $paid = $this->paidAmountForDebt($debt);

        if ($amount > 0 && $paid >= $amount) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial_paid';
        }

        return $this->activeBillItem($debt) ? 'billed' : 'open';
    }

    private function syncDebtStatus(RentToRentDebt $debt): void
    {
        $debt = $debt->fresh($this->debtDetailRelations());
        $amount = $this->displayAmount($debt);
        $paid = $this->paidAmountForDebt($debt);

        $status = match (true) {
            $debt->status === 'cancelled' => 'cancelled',
            $amount > 0 && $paid >= $amount => 'paid',
            $paid > 0 => 'partial_paid',
            (bool) $this->activeBillItem($debt) => 'billed',
            default => 'open',
        };

        $debt->update(['status' => $status]);
        $this->refreshDebtCache($debt->fresh($this->debtDetailRelations()));
    }

    private function syncAfterPaymentVoid(RentToRentPayment $payment): void
    {
        $bill = $payment->bill?->fresh($this->billRelations());
        if ($bill) {
            $paidAmount = $this->billPaidAmount($bill);
            $bill->update([
                'paid_amount' => $paidAmount,
                'status' => $this->billStatus((int) $bill->total_amount, $paidAmount, $bill->sent_at !== null),
            ]);

            foreach ($bill->items as $item) {
                if ($item->debt) {
                    $this->syncDebtStatus($item->debt);
                }
            }
        }

        foreach ($payment->allocations as $allocation) {
            if ($allocation->debt) {
                $this->syncDebtStatus($allocation->debt);
            }
        }
    }

    public function billPaidAmount(RentToRentBill $bill): int
    {
        if (! $bill->relationLoaded('payments')) {
            $bill->load('payments');
        }

        return (int) $bill->payments
            ->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided')
            ->sum('amount');
    }

    public function refreshDebtCache(RentToRentDebt $debt): void
    {
        $total = $this->displayAmount($debt);
        $paid = $this->paidAmountForDebt($debt);
        $status = $this->paymentStatusForDebt($debt);

        $debt->updateQuietly([
            'cached_total_amount' => $total,
            'cached_paid_amount' => $paid,
            'cached_payment_status' => $status,
        ]);
    }

    public function syncMissingDebts(array $filters = []): void
    {
        BookingDetail::query()
            ->with(['booking', 'unit.rentalOwner'])
            ->whereHas('booking', function ($query) use ($filters) {
                $query->whereNotIn('status', ['batal', 'follow_up', 'confirm'])
                    ->when($filters['tenant_id'] ?? null, fn($booking, $tenantId) => $booking->where('tenant_id', $tenantId))
                    ->when($filters['branch_id'] ?? null, fn($booking, $branchId) => $booking->where('branch_id', $branchId));
            })
            ->where('status', '!=', 'batal')
            ->whereHas('unit.rentalOwner', fn($query) => $query->where('is_owner', false))
            ->chunkById(100, function (Collection $details) {
                $details->each(fn(BookingDetail $detail) => $this->syncDetail($detail));
            });
    }

    private function debtListRelations(): array
    {
        return [
            'rentalOwner',
            'booking.customer',
            'bookingDetail.unit.rentalOwner',
            'billItems.bill',
        ];
    }

    private function debtDetailRelations(): array
    {
        return [
            ...$this->debtListRelations(),
            'billItems.allocations.payment',
            'paymentAllocations.payment.paymentAccount',
            'paymentAllocations.payment.bill',
        ];
    }

    private function billRelations(): array
    {
        return [
            'rentalOwner',
            'branch',
            'items.debt.booking.customer',
            'items.debt.bookingDetail.unit.rentalOwner',
            'items.allocations.payment',
            'payments.paymentAccount',
            'payments.creator',
            'voidRequester',
            'voidApprover',
            'voidRejecter',
        ];
    }

    private function activeBillItem(RentToRentDebt $debt)
    {
        if (! $debt->relationLoaded('billItems')) {
            $debt->load('billItems.bill');
        }

        return $debt->billItems
            ->filter(fn($item) => $item->bill && ! in_array($item->bill->status, ['void'], true))
            ->sortByDesc('created_at')
            ->first();
    }

    private function hasLockedBillItem(RentToRentDebt $debt): bool
    {
        return (bool) $this->activeBillItem($debt);
    }



    private function billStatus(int $totalAmount, int $paidAmount, bool $wasSent): string
    {
        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial_paid';
        }

        return $wasSent ? 'sent' : 'generated';
    }

    private function formatPaymentHistory(RentToRentPayment $payment): array
    {
        $directDebts = $payment->relationLoaded('allocations')
            ? $payment->allocations->pluck('debt')->filter()
            : collect();

        return [
            'id' => $payment->id,
            'bill_id' => $payment->rent_to_rent_bill_id,
            'bill_number' => $payment->bill?->bill_number,
            'owner_name' => $payment->bill?->rentalOwner?->nama
                ?? $directDebts->pluck('rentalOwner.nama')->filter()->unique()->join(', '),
            'booking_codes' => $payment->bill?->items?->pluck('debt.booking.kode_booking')->filter()->unique()->values()
                ?? $directDebts->pluck('booking.kode_booking')->filter()->unique()->values(),
            'payment_account_name' => $payment->paymentAccount
                ? trim($payment->paymentAccount->nama_bank.' '.$payment->paymentAccount->nomor_rekening)
                : null,
            'created_by_name' => $payment->creator?->name,
            'amount' => (int) $payment->amount,
            'paid_at' => $payment->paid_at?->toISOString(),
            'voided_at' => $payment->voided_at?->toISOString(),
            'void_reason' => $payment->void_reason,
            'void_requested_at' => $payment->void_requested_at?->toISOString(),
            'void_approved_at' => $payment->void_approved_at?->toISOString(),
            'void_rejected_at' => $payment->void_rejected_at?->toISOString(),
            'void_rejection_note' => $payment->void_rejection_note,
            'created_at' => $payment->created_at?->toISOString(),
            'status' => $payment->status ?? 'active',
        ];
    }

    private function nextBillNumber(int $branchId): string
    {
        $prefix = 'RTR-' . date('Ym') . '-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-';
        $lastBill = RentToRentBill::where('branch_id', $branchId)
            ->where('bill_number', 'like', $prefix . '%')
            ->orderByDesc('bill_number')
            ->first();

        $next = $lastBill ? ((int) substr($lastBill->bill_number, -5)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function newPublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (RentToRentBill::where('public_token', $token)->exists());

        return $token;
    }
}
