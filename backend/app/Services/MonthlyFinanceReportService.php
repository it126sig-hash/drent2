<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DriverOperationalFund;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\Refund;
use App\Models\RentToRentPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MonthlyFinanceReportService
{
    public function __construct(private BookingBillingService $billingService) {}

    public function report(array $filters = []): array
    {
        $user = Auth::user();
        [$dateFrom, $dateTo] = $this->period($filters);
        $branchId = $this->branchId($filters, $user);
        $accountId = isset($filters['payment_account_id']) ? (int) $filters['payment_account_id'] : null;

        $accounts = PaymentAccount::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($accountId, fn ($query) => $query->where('id', $accountId))
            ->orderBy('nama_bank')
            ->get();

        $bookingRevenue = $this->bookingRevenue($dateFrom, $dateTo, $user, $branchId);
        $directPayments = $this->directBookingPayments($dateFrom, $dateTo, $user, $branchId, $accountId);
        $invoicePayments = $this->invoicePayments($dateFrom, $dateTo, $user, $branchId, $accountId);
        $refunds = $this->refunds($dateFrom, $dateTo, $user, $branchId, $accountId);
        $operationalFunds = $this->operationalFunds($dateFrom, $dateTo, $user, $branchId, $accountId);
        $rentToRentPayments = $this->rentToRentPayments($dateFrom, $dateTo, $user, $branchId, $accountId);
        $accountTransactions = $this->accountTransactions($dateFrom, $dateTo, $user, $branchId, $accountId);

        $byAccount = $accounts->map(function (PaymentAccount $account) use (
            $directPayments,
            $invoicePayments,
            $refunds,
            $operationalFunds,
            $rentToRentPayments,
            $accountTransactions
        ) {
            $rentalIncome = $this->sumForAccount($directPayments, $account->id) + $this->sumForAccount($invoicePayments, $account->id);
            $otherIncome = $this->sumTransactions($accountTransactions, $account->id, 'other_income');
            $refundTotal = $this->sumForAccount($refunds, $account->id);
            $operationalTotal = $this->sumForAccount($operationalFunds, $account->id);
            $rentToRentTotal = $this->sumForAccount($rentToRentPayments, $account->id);
            $otherExpense = $this->sumTransactions($accountTransactions, $account->id, 'other_expense');
            $transferIn = $this->sumTransactions($accountTransactions, $account->id, 'transfer_in');
            $transferOut = $this->sumTransactions($accountTransactions, $account->id, 'transfer_out');
            $adjustment = $accountTransactions
                ->where('payment_account_id', $account->id)
                ->where('type', 'balance_adjustment')
                ->sum('signed_amount');
            $businessExpense = $refundTotal + $operationalTotal + $rentToRentTotal + $otherExpense;
            $netCash = $rentalIncome + $otherIncome - $businessExpense;
            $netMovement = $netCash + $transferIn - $transferOut + $adjustment;

            return [
                'payment_account' => $this->accountPayload($account),
                'rental_income' => $rentalIncome,
                'other_income' => $otherIncome,
                'refunds' => $refundTotal,
                'operational_funds' => $operationalTotal,
                'rent_to_rent_payments' => $rentToRentTotal,
                'other_expense' => $otherExpense,
                'business_expense' => $businessExpense,
                'transfer_in' => $transferIn,
                'transfer_out' => $transferOut,
                'balance_adjustment' => (int) $adjustment,
                'net_cash' => $netCash,
                'net_movement' => $netMovement,
                'estimated_opening_balance' => (int) $account->current_balance - $netMovement,
            ];
        })->values();

        $entries = collect()
            ->merge($this->paymentEntries($directPayments, 'booking_payment', 'Pembayaran Booking', 1))
            ->merge($this->paymentEntries($invoicePayments, 'invoice_payment', 'Pembayaran Invoice', 1))
            ->merge($this->paymentEntries($refunds, 'refund', 'Refund', -1))
            ->merge($this->paymentEntries($operationalFunds, 'operational_fund', 'Dana Operasional', -1))
            ->merge($this->paymentEntries($rentToRentPayments, 'rent_to_rent_payment', 'Bayar Rent-to-Rent', -1))
            ->merge($this->accountTransactionEntries($accountTransactions))
            ->sortByDesc('happened_at')
            ->values()
            ->all();

        $totalRentalIncome = (int) $byAccount->sum('rental_income');
        $totalOtherIncome = (int) $byAccount->sum('other_income');
        $totalExpense = (int) $byAccount->sum('business_expense');

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'month' => (int) $dateFrom->month,
                'year' => (int) $dateFrom->year,
                'label' => $dateFrom->translatedFormat('F Y'),
            ],
            'summary' => [
                'booking_revenue' => (int) $bookingRevenue,
                'rental_income' => $totalRentalIncome,
                'other_income' => $totalOtherIncome,
                'business_expense' => $totalExpense,
                'net_cash' => $totalRentalIncome + $totalOtherIncome - $totalExpense,
                'transfer_in' => (int) $byAccount->sum('transfer_in'),
                'transfer_out' => (int) $byAccount->sum('transfer_out'),
                'balance_adjustment' => (int) $byAccount->sum('balance_adjustment'),
                'total_current_balance' => (int) $accounts->sum('current_balance'),
            ],
            'accounts' => $byAccount,
            'entries' => $entries,
        ];
    }

    private function period(array $filters): array
    {
        $now = now();
        $year = (int) ($filters['year'] ?? $now->year);
        $month = (int) ($filters['month'] ?? $now->month);
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    private function branchId(array $filters, User $user): ?int
    {
        if ($user->role !== 'superadmin') {
            return $user->branch_id;
        }

        return isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }

    private function scopeTenantBranch(Builder $query, User $user, ?int $branchId): Builder
    {
        $query->where('tenant_id', $user->tenant_id);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private function bookingRevenue(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): int
    {
        return (int) $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->with(['bookingDetails.costs', 'payments'])
            ->where('status', 'selesai')
            ->where(function (Builder $query) use ($dateFrom, $dateTo) {
                $query->whereBetween('completed_at', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $fallback) use ($dateFrom, $dateTo) {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('updated_at', [$dateFrom, $dateTo]);
                    });
            })
            ->get()
            ->sum(fn (Booking $booking) => $this->billingService->totalTagihan($booking));
    }

    private function directBookingPayments(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return BookingPayment::query()
            ->with(['booking.customer', 'paymentAccount'])
            ->whereNull('invoice_payment_id')
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', '!=', 'voided'))
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->whereHas('booking', fn (Builder $booking) => $this->scopeTenantBranch($booking, $user, $branchId))
            ->get();
    }

    private function invoicePayments(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return Payment::query()
            ->with(['invoice.bookings.customer', 'paymentAccount'])
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->whereHas('invoice', fn (Builder $invoice) => $this->scopeTenantBranch($invoice, $user, $branchId)->where('status', '!=', 'void'))
            ->get();
    }

    private function refunds(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return Refund::query()
            ->with(['booking.customer', 'paymentAccount'])
            ->whereBetween('refunded_at', [$dateFrom, $dateTo])
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->whereHas('booking', fn (Builder $booking) => $this->scopeTenantBranch($booking, $user, $branchId))
            ->get();
    }

    private function operationalFunds(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return DriverOperationalFund::query()
            ->with(['booking.customer', 'paymentAccount'])
            ->whereNotNull('payment_account_id')
            ->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed'])
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$dateFrom, $dateTo])
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->where('tenant_id', $user->tenant_id)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();
    }

    private function rentToRentPayments(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return RentToRentPayment::query()
            ->with(['bill.rentalOwner', 'allocations.debt.booking.customer', 'paymentAccount'])
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', '!=', 'voided'))
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$dateFrom, $dateTo])
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->where(function (Builder $query) use ($user, $branchId) {
                $query->whereHas('bill', fn (Builder $bill) => $this->scopeTenantBranch($bill, $user, $branchId))
                    ->orWhereHas('allocations.debt', fn (Builder $debt) => $this->scopeTenantBranch($debt, $user, $branchId));
            })
            ->get();
    }

    private function accountTransactions(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId, ?int $accountId): Collection
    {
        return PaymentAccountTransaction::query()
            ->with(['paymentAccount', 'relatedPaymentAccount', 'financeCategory', 'creator'])
            ->where('tenant_id', $user->tenant_id)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($accountId, fn ($query) => $query->where('payment_account_id', $accountId))
            ->whereBetween('transaction_at', [$dateFrom, $dateTo])
            ->get();
    }

    private function sumForAccount(Collection $rows, int $accountId): int
    {
        return (int) $rows->where('payment_account_id', $accountId)->sum('amount');
    }

    private function sumTransactions(Collection $rows, int $accountId, string $type): int
    {
        return (int) $rows->where('payment_account_id', $accountId)->where('type', $type)->sum('amount');
    }

    private function accountPayload(PaymentAccount $account): array
    {
        return [
            'id' => $account->id,
            'nama_bank' => $account->nama_bank,
            'nomor_rekening' => $account->nomor_rekening,
            'atas_nama' => $account->atas_nama,
            'current_balance' => (int) $account->current_balance,
            'is_active' => (bool) $account->is_active,
        ];
    }

    private function paymentEntries(Collection $rows, string $sourceType, string $label, int $direction): Collection
    {
        return $rows->map(fn ($row) => [
            'source_type' => $sourceType,
            'source_id' => $row->id,
            'label' => $label,
            'payment_account_id' => $row->payment_account_id,
            'payment_account_name' => $this->accountName($row->paymentAccount),
            'amount' => (int) $row->amount,
            'signed_amount' => $direction * (int) $row->amount,
            'happened_at' => ($row->paid_at ?? $row->refunded_at ?? $row->created_at)?->toISOString(),
            'reference' => $this->reference($row),
            'description' => $row->catatan ?? $row->keterangan ?? $row->notes ?? null,
        ]);
    }

    private function accountTransactionEntries(Collection $rows): Collection
    {
        return $rows->map(fn (PaymentAccountTransaction $row) => [
            'source_type' => 'account_transaction',
            'source_id' => $row->id,
            'label' => $this->transactionLabel($row->type),
            'payment_account_id' => $row->payment_account_id,
            'payment_account_name' => $this->accountName($row->paymentAccount),
            'amount' => (int) $row->amount,
            'signed_amount' => (int) $row->signed_amount,
            'happened_at' => $row->transaction_at?->toISOString(),
            'reference' => $row->financeCategory?->name ?? $this->accountName($row->relatedPaymentAccount),
            'description' => $row->description,
            'type' => $row->type,
        ]);
    }

    private function accountName($account): ?string
    {
        return $account ? trim($account->nama_bank.' '.$account->nomor_rekening) : null;
    }

    private function reference($row): ?string
    {
        if ($row instanceof Payment) {
            return $row->invoice?->invoice_number;
        }

        return $row->booking?->kode_booking
            ?? $row->bill?->bill_number
            ?? $row->booking?->customer?->nama
            ?? null;
    }

    private function transactionLabel(string $type): string
    {
        return match ($type) {
            'transfer_out' => 'Transfer Keluar',
            'transfer_in' => 'Transfer Masuk',
            'other_income' => 'Pemasukan Lain-lain',
            'other_expense' => 'Pengeluaran Lain-lain',
            'balance_adjustment' => 'Adjust Saldo',
            default => $type,
        };
    }
}
