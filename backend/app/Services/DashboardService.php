<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Invoice;
use App\Models\PaymentAccountTransaction;
use App\Models\PhysicalCheck;
use App\Models\RentToRentBill;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    private const OPEN_BOOKING_STATUSES = ['follow_up', 'confirm', 'waiting_list', 'rental_unit'];
    private const LEADERBOARD_STATUSES = ['Normal', 'Member', 'Rent to Rent', 'Corporate'];

    public function summary(array $filters, User $user): array
    {
        [$dateFrom, $dateTo] = $this->period($filters);
        $branchId = $this->branchId($filters, $user);

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'label' => $this->periodLabel($dateFrom, $dateTo),
            ],
            'kpis' => $this->kpis($dateFrom, $dateTo, $user, $branchId),
            'booking_today' => $this->bookingToday($dateFrom, $dateTo, $user, $branchId),
            'armada_status' => $this->armadaStatus($user, $branchId),
            'finance_snapshot' => $this->financeSnapshot($dateFrom, $dateTo, $user, $branchId),
            'cashflow_summary' => $this->cashflowSummary($dateFrom, $dateTo, $user, $branchId),
            'alerts' => $this->alerts($user, $branchId),
            'repeat_order_leaderboards' => $this->repeatOrderLeaderboards($dateFrom, $dateTo, $user, $branchId),
        ];
    }

    private function period(array $filters): array
    {
        $dateFrom = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();

        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfMonth();

        return [$dateFrom, $dateTo];
    }

    private function branchId(array $filters, User $user): ?int
    {
        if ($user->role !== 'superadmin') {
            return $user->branch_id;
        }

        return isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }

    private function periodLabel(Carbon $dateFrom, Carbon $dateTo): string
    {
        if ($dateFrom->isSameDay($dateTo)) {
            return $dateFrom->translatedFormat('d M Y');
        }

        return $dateFrom->translatedFormat('d M Y').' - '.$dateTo->translatedFormat('d M Y');
    }

    private function kpis(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): array
    {
        $openRentToRentBills = $this->scopeTenantBranch(RentToRentBill::query(), $user, $branchId)
            ->whereNotIn('status', ['paid', 'void'])
            ->whereColumn('paid_amount', '<', 'total_amount');
        $openRentToRentAmount = (clone $openRentToRentBills)
            ->get()
            ->sum(fn (RentToRentBill $bill) => max(0, (int) $bill->total_amount - (int) $bill->paid_amount));
        $openRentToRentCount = (clone $openRentToRentBills)->count();

        $activeBookingCount = $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->whereIn('status', self::OPEN_BOOKING_STATUSES)
            ->count();

        $completedBookingCount = $this->completedInPeriodQuery($dateFrom, $dateTo, $user, $branchId)->count();
        $revenueAmount = $this->paymentInPeriodQuery($dateFrom, $dateTo, $user, $branchId)->sum('amount');

        $outstandingQuery = $this->outstandingBookingsQuery($user, $branchId);
        $outstandingAmount = (clone $outstandingQuery)->sum('cached_sisa_tagihan');

        $notGeneratedInvoiceCount = $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->where('cached_sisa_tagihan', '>', 0)
            ->where('status', '!=', 'batal')
            ->whereDoesntHave('invoices', function (Builder $query) {
                $query->whereNotIn('status', ['void', 'paid'])
                    ->whereColumn('paid_amount', '<', 'total_amount');
            })
            ->count();

        return [
            [
                'key' => 'active_bookings',
                'label' => 'Booking Aktif',
                'value' => $activeBookingCount,
                'display_value' => (string) $activeBookingCount,
                'delta' => 'Follow up sampai rental unit',
                'icon' => 'pi pi-calendar',
                'tone' => 'info',
                'route' => '/bookings',
            ],
            [
                'key' => 'completed_bookings',
                'label' => 'Booking Selesai',
                'value' => $completedBookingCount,
                'display_value' => (string) $completedBookingCount,
                'delta' => 'Periode terpilih',
                'icon' => 'pi pi-check-circle',
                'tone' => 'neutral',
                'route' => '/bookings',
            ],
            [
                'key' => 'revenue',
                'label' => 'Pendapatan Masuk',
                'value' => (int) $revenueAmount,
                'display_value' => $this->formatCurrency((int) $revenueAmount),
                'delta' => 'Pembayaran periode ini',
                'icon' => 'pi pi-wallet',
                'tone' => 'positive',
                'route' => '/finance/transactions',
            ],
            [
                'key' => 'outstanding',
                'label' => 'Piutang Berjalan',
                'value' => (int) $outstandingAmount,
                'display_value' => $this->formatCurrency((int) $outstandingAmount),
                'delta' => (clone $outstandingQuery)->count().' transaksi',
                'icon' => 'pi pi-exclamation-circle',
                'tone' => 'warning',
                'sub_value' => $notGeneratedInvoiceCount . ' transaksi belum dibuat invoice',
                'route' => '/finance/receivables',
            ],
            [
                'key' => 'rent_to_rent_debt',
                'label' => 'Hutang Rent-to-Rent',
                'value' => (int) $openRentToRentAmount,
                'display_value' => $this->formatCurrency((int) $openRentToRentAmount),
                'delta' => "{$openRentToRentCount} tagihan",
                'icon' => 'pi pi-percentage',
                'tone' => 'negative',
                'route' => '/finance/rent-to-rent',
            ],
        ];
    }

    private function bookingToday(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): array
    {
        return $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->with(['customer', 'bookingDetails.unit.rentalOwner', 'bookingDetails.costs'])
            ->whereHas('bookingDetails', function (Builder $detail) use ($dateFrom, $dateTo) {
                $detail->whereBetween('tgl_sewa', [$dateFrom, $dateTo]);
            })
            ->latest('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingRow($booking))
            ->values()
            ->all();
    }

    private function armadaStatus(User $user, ?int $branchId): array
    {
        $unitQuery = $this->scopeTenantBranch(Unit::query(), $user, $branchId)
            ->whereDoesntHave('rentalOwner', function (Builder $query) {
                $query->where('is_owner', false);
            });
        $total = max(1, (clone $unitQuery)->count());

        $items = [
            'available' => ['label' => 'Tersedia', 'statuses' => ['Aktif'], 'tone' => 'positive'],
            'rented' => ['label' => 'Disewa', 'statuses' => ['Out'], 'tone' => 'warning'],
            'maintenance' => ['label' => 'Maintenance', 'statuses' => ['Dalam Servis'], 'tone' => 'negative'],
            'inactive' => ['label' => 'Tidak Aktif', 'statuses' => ['Tidak Aktif'], 'tone' => 'neutral'],
        ];

        return collect($items)
            ->map(function (array $item, string $key) use ($unitQuery, $total) {
                $count = (clone $unitQuery)->whereIn('status', $item['statuses'])->count();

                return [
                    'key' => $key,
                    'label' => $item['label'],
                    'value' => $count,
                    'percentage' => round(($count / $total) * 100, 1),
                    'tone' => $item['tone'],
                ];
            })
            ->values()
            ->all();
    }

    private function financeSnapshot(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): array
    {
        $outstandingBookings = $this->outstandingBookingsQuery($user, $branchId);
        $openRentToRentBills = $this->scopeTenantBranch(RentToRentBill::query(), $user, $branchId)
            ->whereNotIn('status', ['paid', 'void'])
            ->whereColumn('paid_amount', '<', 'total_amount');

        $latestPayments = $this->paymentInPeriodQuery($dateFrom, $dateTo, $user, $branchId)
            ->with(['booking.customer', 'paymentAccount'])
            ->latest('paid_at')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (BookingPayment $payment) => [
                'id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'booking_code' => $payment->booking?->kode_booking,
                'customer_name' => $payment->booking?->customer?->nama,
                'amount' => (int) $payment->amount,
                'paid_at' => $payment->paid_at?->toISOString(),
                'payment_account' => $payment->paymentAccount?->nama_bank,
            ])
            ->values()
            ->all();

        return [
            'outstanding_count' => (clone $outstandingBookings)->count(),
            'outstanding_amount' => (int) (clone $outstandingBookings)->sum('cached_sisa_tagihan'),
            'open_rent_to_rent_bill_count' => (clone $openRentToRentBills)->count(),
            'open_rent_to_rent_bill_amount' => (int) (clone $openRentToRentBills)
                ->get()
                ->sum(fn (RentToRentBill $bill) => max(0, (int) $bill->total_amount - (int) $bill->paid_amount)),
            'latest_payments' => $latestPayments,
        ];
    }

    private function cashflowSummary(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): array
    {
        $transactions = PaymentAccountTransaction::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereBetween('transaction_at', [$dateFrom, $dateTo])
            ->get();

        $incomeItems = [
            [
                'key' => 'rental_income',
                'label' => 'Pendapatan Rental',
                'amount' => (int) $transactions
                    ->whereIn('type', ['booking_payment_in', 'invoice_payment_in', 'booking_payment_void', 'invoice_payment_void'])
                    ->sum('signed_amount'),
                'tone' => 'positive',
            ],
            [
                'key' => 'other_income',
                'label' => 'Pemasukan Lainnya',
                'amount' => (int) $transactions
                    ->whereIn('type', ['other_income', 'driver_return_in'])
                    ->sum('signed_amount'),
                'tone' => 'info',
            ],
        ];

        $expenseItems = [
            [
                'key' => 'refunds',
                'label' => 'Refund',
                'amount' => (int) -$transactions->whereIn('type', ['refund_out'])->sum('signed_amount'),
                'tone' => 'negative',
            ],
            [
                'key' => 'operational_funds',
                'label' => 'Bon & Operasional',
                'amount' => (int) -$transactions
                    ->whereIn('type', ['driver_fund_out', 'driver_direct_expense_out', 'driver_fund_void', 'driver_direct_expense_void'])
                    ->sum('signed_amount'),
                'tone' => 'warning',
            ],
            [
                'key' => 'rent_to_rent',
                'label' => 'Bayar Rent-to-Rent',
                'amount' => (int) -$transactions
                    ->whereIn('type', ['rent_to_rent_payment_out', 'rent_to_rent_payment_void'])
                    ->sum('signed_amount'),
                'tone' => 'info',
            ],
            [
                'key' => 'other_expense',
                'label' => 'Pengeluaran Lainnya',
                'amount' => (int) -$transactions->whereIn('type', ['other_expense'])->sum('signed_amount'),
                'tone' => 'neutral',
            ],
        ];

        $incomeTotal = collect($incomeItems)->sum('amount');
        $expenseTotal = collect($expenseItems)->sum('amount');

        return [
            'income_total' => (int) $incomeTotal,
            'expense_total' => (int) $expenseTotal,
            'net_cash' => (int) $incomeTotal - (int) $expenseTotal,
            'income_items' => $incomeItems,
            'expense_items' => $expenseItems,
        ];
    }

    private function alerts(User $user, ?int $branchId): array
    {
        $pendingSupervisor = $this->pendingSupervisorCount($user, $branchId);
        $overdueBookings = $this->overdueBookings($user, $branchId);
        $pendingPhysicalChecks = $this->scopeTenantBranch(PhysicalCheck::query(), $user, $branchId)
            ->where('status', 'requested')
            ->count();

        return [
            [
                'key' => 'follow_up',
                'label' => 'Booking perlu follow-up',
                'value' => $this->scopeTenantBranch(Booking::query(), $user, $branchId)
                    ->where('status', 'follow_up')
                    ->count(),
                'tone' => 'info',
                'route' => '/bookings',
            ],
            [
                'key' => 'supervisor_requests',
                'label' => 'Request supervisor pending',
                'value' => $pendingSupervisor,
                'tone' => 'warning',
                'route' => '/supervisor/requests',
            ],
            [
                'key' => 'overdue_units',
                'label' => 'Unit terlambat kembali',
                'value' => $overdueBookings->count(),
                'tone' => 'negative',
                'route' => '/bookings',
            ],
            [
                'key' => 'physical_checks',
                'label' => 'Cek fisik belum lengkap',
                'value' => $pendingPhysicalChecks,
                'tone' => 'neutral',
                'route' => '/physical-checks',
            ],
        ];
    }

    private function repeatOrderLeaderboards(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): array
    {
        $bookings = $this->completedInPeriodQuery($dateFrom, $dateTo, $user, $branchId)
            ->with(['customer', 'bookingDetails.unit.rentalOwner'])
            ->get();

        $leaderboards = collect(self::LEADERBOARD_STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => []])
            ->all();

        foreach (['Normal', 'Member', 'Corporate'] as $status) {
            $leaderboards[$status] = $this->customerLeaderboard(
                $bookings->filter(fn (Booking $booking) => $booking->customer?->status === $status)
            );
        }

        $rentToRentBookings = $bookings->filter(fn (Booking $booking) => $this->rentToRentOwner($booking) || $booking->customer?->status === 'Rent to Rent');
        $leaderboards['Rent to Rent'] = $this->rentToRentLeaderboard($rentToRentBookings);

        return collect($leaderboards)
            ->map(fn (array $items, string $status) => [
                'status' => $status,
                'label' => $status,
                'items' => $items,
            ])
            ->values()
            ->all();
    }

    private function customerLeaderboard(Collection $bookings): array
    {
        return $bookings
            ->filter(fn (Booking $booking) => $booking->customer)
            ->groupBy('customer_id')
            ->map(fn (Collection $group) => $this->leaderboardItem($group, [
                'id' => $group->first()->customer?->id,
                'name' => $group->first()->customer?->nama,
                'contact' => $group->first()->customer?->kontak_1,
                'source' => 'customer',
            ]))
            ->sort(fn (array $a, array $b) => [$b['total_bookings'], $b['latest_booking_at']] <=> [$a['total_bookings'], $a['latest_booking_at']])
            ->values()
            ->take(5)
            ->values()
            ->all();
    }

    private function rentToRentLeaderboard(Collection $bookings): array
    {
        return $bookings
            ->groupBy(function (Booking $booking) {
                $owner = $this->rentToRentOwner($booking);

                if ($owner) {
                    return 'owner-'.$owner['id'];
                }

                return 'customer-'.$booking->customer_id;
            })
            ->map(function (Collection $group) {
                $booking = $group->first();
                $owner = $this->rentToRentOwner($booking);

                return $this->leaderboardItem($group, [
                    'id' => $owner['id'] ?? $booking->customer?->id,
                    'name' => $owner['name'] ?? $booking->customer?->nama,
                    'contact' => $owner['contact'] ?? $booking->customer?->kontak_1,
                    'source' => $owner ? 'rental_owner' : 'customer',
                ]);
            })
            ->sort(fn (array $a, array $b) => [$b['total_bookings'], $b['latest_booking_at']] <=> [$a['total_bookings'], $a['latest_booking_at']])
            ->values()
            ->take(5)
            ->values()
            ->all();
    }

    private function leaderboardItem(Collection $bookings, array $person): array
    {
        $latestBooking = $bookings->sortByDesc(fn (Booking $booking) => $this->bookingCompletedAt($booking)?->timestamp ?? 0)->first();

        return [
            'id' => $person['id'],
            'name' => $person['name'] ?? '-',
            'contact' => $person['contact'] ?? null,
            'source' => $person['source'],
            'total_bookings' => $bookings->count(),
            'latest_booking_at' => $this->bookingCompletedAt($latestBooking)?->toISOString(),
            'latest_booking_code' => $latestBooking?->kode_booking,
        ];
    }

    private function bookingRow(Booking $booking): array
    {
        $details = $booking->bookingDetails;
        $start = $details->min('tgl_sewa');
        $end = $details->max('tgl_kembali');
        $isLate = $booking->status === 'rental_unit' && $end && Carbon::parse($end)->isPast();

        $billingService = app(\App\Services\BookingBillingService::class);
        $totalTagihan = $billingService->totalTagihan($booking);
        $hasUnit = $details->whereNotNull('unit_id')->isNotEmpty();

        return [
            'id' => $booking->id,
            'kode_booking' => $booking->kode_booking,
            'customer_name' => $booking->customer?->nama,
            'customer_status' => $booking->customer?->status,
            'status' => $booking->status,
            'amount' => (int) $booking->harga_dealing,
            'tgl_sewa' => $start ? Carbon::parse($start)->toISOString() : null,
            'tgl_kembali' => $end ? Carbon::parse($end)->toISOString() : null,
            'is_late' => $isLate,
            'unit_label' => $details
                ->map(fn ($detail) => $detail->unit ? trim($detail->unit->merk.' '.$detail->unit->tipe.' '.$detail->unit->no_polisi) : $detail->unit_placeholder)
                ->filter()
                ->unique()
                ->values()
                ->join(', '),
            'unit_id' => $details->first()?->unit_id,
            'has_unit' => $hasUnit,
            'total_biaya' => [
                'total' => $totalTagihan,
            ],
        ];
    }

    private function completedInPeriodQuery(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): Builder
    {
        return $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->where('status', 'selesai')
            ->where(function (Builder $query) use ($dateFrom, $dateTo) {
                $query->whereBetween('completed_at', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $fallback) use ($dateFrom, $dateTo) {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('updated_at', [$dateFrom, $dateTo]);
                    });
            });
    }

    private function paymentInPeriodQuery(Carbon $dateFrom, Carbon $dateTo, User $user, ?int $branchId): Builder
    {
        return BookingPayment::query()
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'voided');
            })
            ->whereBetween('paid_at', [$dateFrom, $dateTo])
            ->whereHas('booking', fn (Builder $booking) => $this->scopeTenantBranch($booking, $user, $branchId));
    }

    private function outstandingBookingsQuery(User $user, ?int $branchId): Builder
    {
        return $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->where('cached_sisa_tagihan', '>', 0)
            ->whereNotIn('status', ['batal']);
    }

    private function overdueBookings(User $user, ?int $branchId): Collection
    {
        return $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->with('bookingDetails')
            ->where('status', 'rental_unit')
            ->get()
            ->filter(function (Booking $booking) {
                $lastReturn = $booking->bookingDetails->max('tgl_kembali');

                return $lastReturn && Carbon::parse($lastReturn)->isPast();
            })
            ->values();
    }

    private function pendingSupervisorCount(User $user, ?int $branchId): int
    {
        $paymentVoidCount = BookingPayment::query()
            ->where('status', 'void_requested')
            ->whereHas('booking', fn (Builder $booking) => $this->scopeTenantBranch($booking, $user, $branchId))
            ->count();

        $returnRequestCount = $this->scopeTenantBranch(Booking::query(), $user, $branchId)
            ->where('rental_unit_return_status', 'pending')
            ->count();

        $rentToRentVoidCount = $this->scopeTenantBranch(RentToRentBill::query(), $user, $branchId)
            ->where('status', 'void_requested')
            ->count();

        return $paymentVoidCount + $returnRequestCount + $rentToRentVoidCount;
    }

    private function rentToRentOwner(Booking $booking): ?array
    {
        $owner = $booking->bookingDetails
            ->map(fn ($detail) => $detail->unit?->rentalOwner)
            ->filter(fn ($owner) => $owner && $owner->is_owner === false)
            ->first();

        if (! $owner) {
            return null;
        }

        return [
            'id' => $owner->id,
            'name' => $owner->nama,
            'contact' => $owner->kontak_1,
        ];
    }

    private function bookingCompletedAt(?Booking $booking): ?Carbon
    {
        if (! $booking) {
            return null;
        }

        return $booking->completed_at
            ? Carbon::parse($booking->completed_at)
            : Carbon::parse($booking->updated_at);
    }

    private function scopeTenantBranch(Builder $query, User $user, ?int $branchId): Builder
    {
        $query->where('tenant_id', $user->tenant_id);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private function formatCurrency(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
