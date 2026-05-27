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

        $transactions = $this->accountTransactions($dateFrom, $dateTo, $user, $branchId, $accountId);
        $bookingRevenue = $this->bookingRevenue($dateFrom, $dateTo, $user, $branchId);

        $byAccount = $accounts->map(function (PaymentAccount $account) use ($transactions) {
            $accTx = $transactions->where('payment_account_id', $account->id);

            // Income (sum signed_amount)
            $rentalIncome = $accTx->whereIn('type', ['booking_payment_in', 'invoice_payment_in', 'booking_payment_void', 'invoice_payment_void'])->sum('signed_amount');
            $otherIncome = $accTx->whereIn('type', ['other_income', 'driver_return_in'])->sum('signed_amount');

            // Expense (sum negative of signed_amount since signed_amount is negative for expenses)
            $refundTotal = -$accTx->whereIn('type', ['refund_out'])->sum('signed_amount');
            $operationalTotal = -$accTx->whereIn('type', ['driver_fund_out', 'driver_direct_expense_out', 'driver_fund_void', 'driver_direct_expense_void'])->sum('signed_amount');
            $rentToRentTotal = -$accTx->whereIn('type', ['rent_to_rent_payment_out', 'rent_to_rent_payment_void'])->sum('signed_amount');
            $otherExpense = -$accTx->whereIn('type', ['other_expense'])->sum('signed_amount');

            $transferIn = $accTx->where('type', 'transfer_in')->sum('signed_amount');
            $transferOut = -$accTx->where('type', 'transfer_out')->sum('signed_amount');
            $adjustment = $accTx->where('type', 'balance_adjustment')->sum('signed_amount');

            $businessExpense = $refundTotal + $operationalTotal + $rentToRentTotal + $otherExpense;
            $netCash = $rentalIncome + $otherIncome - $businessExpense;
            $netMovement = $netCash + $transferIn - $transferOut + $adjustment;

            return [
                'payment_account' => $this->accountPayload($account),
                'rental_income' => (int) $rentalIncome,
                'other_income' => (int) $otherIncome,
                'refunds' => (int) $refundTotal,
                'operational_funds' => (int) $operationalTotal,
                'rent_to_rent_payments' => (int) $rentToRentTotal,
                'other_expense' => (int) $otherExpense,
                'business_expense' => (int) $businessExpense,
                'transfer_in' => (int) $transferIn,
                'transfer_out' => (int) $transferOut,
                'balance_adjustment' => (int) $adjustment,
                'net_cash' => (int) $netCash,
                'net_movement' => (int) $netMovement,
                'estimated_opening_balance' => (int) $account->current_balance - (int) $netMovement,
            ];
        })->values();

        $entries = $this->accountTransactionEntries($transactions)
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

        if ($row instanceof RentToRentPayment) {
            return $row->bill?->bill_number 
                ?? $row->allocations?->first()?->debt?->booking?->kode_booking;
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
            'rent_to_rent_payment_out' => 'Bayar Rent-to-Rent',
            'rent_to_rent_payment_void' => 'Void Bayar Rent-to-Rent',
            default => $type,
        };
    }

    private function generateRentToRentDescription(RentToRentPayment $row): string
    {
        if ($row->bill) {
            $ownerName = $row->bill->rentalOwner?->nama ?? 'Owner';
            return "Pembayaran tagihan {$row->bill->bill_number} kepada {$ownerName}";
        }

        $firstAllocation = $row->allocations?->first();
        if ($firstAllocation && $firstAllocation->debt) {
            $ownerName = $firstAllocation->debt->rentalOwner?->nama ?? 'Owner';
            $bookingCode = $firstAllocation->debt->booking?->kode_booking;
            if ($bookingCode) {
                return "Pembayaran rent-to-rent ke {$ownerName} untuk booking {$bookingCode}";
            }
            return "Pembayaran rent-to-rent ke {$ownerName}";
        }

        return "Pembayaran rent-to-rent";
    }
}
