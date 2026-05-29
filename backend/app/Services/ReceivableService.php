<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PaymentAccountTransactionService;

class ReceivableService
{
    public function __construct(
        private BookingBillingService $billingService,
        private PaymentAccountTransactionService $transactionService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $paginator = Booking::query()
            ->with([
                'customer.member',
                'bookingDetails.unit.rentalOwner',
                'bookingDetails.costs',
                'payments',
                'invoices' => fn($query) => $query
                    ->where('status', '!=', 'void')
                    ->with(['payments.paymentAccount', 'payments.bookingPayments', 'creator', 'sentBy'])
                    ->latest('generated_at'),
            ])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['kota'] ?? null, fn($query, $kota) => $query->where('kota', $kota))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_booking', 'like', "%{$search}%")
                        ->orWhere('tujuan', 'like', "%{$search}%")
                        ->orWhere('kota', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn($customer) => $customer->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('bookingDetails.unit', function ($unit) use ($search) {
                            $unit->where('merk', 'like', "%{$search}%")
                                ->orWhere('tipe', 'like', "%{$search}%")
                                ->orWhere('no_polisi', 'like', "%{$search}%");
                        })
                        ->orWhereHas('invoices', fn($invoice) => $invoice->where('invoice_number', 'like', "%{$search}%"));
                });
            })
            ->whereNotIn('status', ['batal'])
            // Filter utama pakai cached column — O(log n) via index, bukan full-scan
            ->when(
                ($filters['invoice_status'] ?? null) !== 'changed',
                fn($query) => $query->where('cached_sisa_tagihan', '>', 0)
            )
            ->when($filters['invoice_status'] ?? null, function ($query, $status) {
                if (in_array($status, ['generated', 'changed'], true)) {
                    $query->whereHas('invoices', fn($invoice) => $invoice
                        ->whereNotIn('status', ['void', 'paid'])
                        ->whereColumn('paid_amount', '<', 'total_amount'));
                }

                if ($status === 'not_generated') {
                    $query->whereDoesntHave('invoices', fn($invoice) => $invoice
                        ->whereNotIn('status', ['void', 'paid'])
                        ->whereColumn('paid_amount', '<', 'total_amount'));
                }
            })
            ->latest()
            ->paginate($perPage);

        // Enrich hanya rows pada halaman ini (max $perPage rows, bukan semua data)
        $paginator->getCollection()->transform(function (Booking $booking) use ($filters) {
            $totalTagihan = $this->billingService->totalTagihan($booking);
            $totalPayments = $this->billingService->paidAmount($booking);
            $sisaTagihan = max(0, $totalTagihan - $totalPayments);

            if ((int) $booking->cached_sisa_tagihan !== (int) $sisaTagihan) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['cached_sisa_tagihan' => (int) $sisaTagihan]);
                $booking->cached_sisa_tagihan = (int) $sisaTagihan;
            }

            $booking->total_tagihan = $totalTagihan;
            $booking->total_payments = $totalPayments;
            $booking->sisa_tagihan = $sisaTagihan;
            $booking->latest_active_invoice = $booking->invoices
                ->first(fn(Invoice $invoice) => $invoice->status !== 'paid' && $this->invoiceRemaining($invoice) > 0);
            $booking->invoice_reconciliation = $booking->latest_active_invoice
                ? $this->invoiceReconciliation($booking->latest_active_invoice)
                : null;
            $booking->display_detail = $booking->bookingDetails->firstWhere('status', 'aktif')
                ?? $booking->bookingDetails->firstWhere('detail_type', 'initial')
                ?? $booking->bookingDetails->first();

            return $booking;
        });

        if (($filters['invoice_status'] ?? null) !== 'changed') {
            $paginator->setCollection($paginator->getCollection()
                ->filter(fn(Booking $booking) => $booking->sisa_tagihan > 0)
                ->values());
        }

        // Filter 'changed' masih dilakukan di PHP (hanya dari rows halaman saat ini)
        if (($filters['invoice_status'] ?? null) === 'changed') {
            $filtered = $paginator->getCollection()->filter(
                fn(Booking $booking) => (bool) ($booking->invoice_reconciliation['is_changed'] ?? false)
            )->values();
            $paginator->setCollection($filtered);
        }

        return $paginator;
    }

    public function invoices(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Invoice::query()
            ->with(['bookings.customer', 'payments.paymentAccount', 'payments.bookingPayments', 'creator', 'sentBy'])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['kota'] ?? null, fn($query, $kota) => $query->whereHas('bookings', fn($q) => $q->where('kota', $kota)))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('bookings', fn($booking) => $booking->where('kode_booking', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn($customer) => $customer->where('nama', 'like', "%{$search}%")));
                });
            })
            ->latest('generated_at')
            ->paginate($perPage);
    }

    public function invoiceReconciliation(Invoice $invoice): array
    {
        $amounts = $this->currentInvoiceBookingAmounts($invoice);
        $currentTotalAmount = (int) $amounts->sum();
        $differenceAmount = $currentTotalAmount - (int) $invoice->total_amount;

        return [
            'current_total_amount' => $currentTotalAmount,
            'difference_amount' => $differenceAmount,
            'is_changed' => $differenceAmount !== 0,
            'change_type' => $differenceAmount > 0 ? 'increase' : ($differenceAmount < 0 ? 'decrease' : 'none'),
            'requires_sent_confirmation' => $invoice->sent_at !== null && $differenceAmount !== 0,
        ];
    }

    public function refreshInvoiceAmount(Invoice $invoice, bool $confirmSentRevision = false): Invoice
    {
        return DB::transaction(function () use ($invoice, $confirmSentRevision) {
            $invoice = Invoice::query()
                ->with(['payments.paymentAccount', 'payments.bookingPayments'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if (! in_array($invoice->status, ['generated', 'partial_paid'], true)) {
                throw new \InvalidArgumentException('Hanya invoice generated atau partial paid yang bisa diperbarui.');
            }

            if ($invoice->sent_at && ! $confirmSentRevision) {
                throw new \InvalidArgumentException('Invoice sudah pernah dikirim. Konfirmasi revisi invoice diperlukan.');
            }

            $bookingIds = $invoice->bookings->pluck('id')->all();
            if ($bookingIds) {
                Booking::query()
                    ->whereIn('id', $bookingIds)
                    ->lockForUpdate()
                    ->get();

                $invoice->load(['payments.paymentAccount', 'payments.bookingPayments']);
            }

            $amounts = $this->currentInvoiceBookingAmounts($invoice);
            $totalAmount = (int) $amounts->sum();
            $paidAmount = $this->invoicePaidAmount($invoice);

            foreach ($amounts as $bookingId => $amount) {
                $invoice->bookings()->updateExistingPivot($bookingId, ['amount' => (int) $amount]);
            }

            $invoice->update([
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'status' => $this->invoiceStatus($totalAmount, $paidAmount),
            ]);

            return $invoice->fresh(['bookings.customer', 'payments.paymentAccount', 'payments.bookingPayments']);
        });
    }

    public function paymentHistory(array $filters = []): array
    {
        $view = $filters['view'] ?? 'all';
        $latest = collect();
        $groups = collect();
        $latestMeta = $this->emptyPaginatorMeta((int) ($filters['latest_per_page'] ?? $filters['latest_limit'] ?? 20));
        $groupMeta = $this->emptyPaginatorMeta((int) ($filters['group_per_page'] ?? $filters['group_limit'] ?? 15));

        if (in_array($view, ['all', 'latest'], true)) {
            $latestPaginator = $this->latestPaymentHistory($filters);
            $latest = $latestPaginator->getCollection();
            $latestMeta = $this->paginatorMeta($latestPaginator);
        }

        if (in_array($view, ['all', 'group'], true)) {
            $groupPaginator = $this->groupedPaymentHistory($filters);
            $groups = $groupPaginator->getCollection();
            $groupMeta = $this->paginatorMeta($groupPaginator);
        }

        return [
            'latest' => $latest,
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

        $invoicePayments = Payment::query()
            ->selectRaw("'invoice' as source_type, payments.id as source_id, payments.paid_at, payments.created_at")
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->whereHas('invoice', fn($invoice) => $invoice->where('tenant_id', $tenantId)))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->whereHas('invoice', fn($invoice) => $invoice->where('branch_id', $branchId)));

        $transactionPayments = BookingPayment::query()
            ->selectRaw("'transaction' as source_type, booking_payments.id as source_id, booking_payments.paid_at, booking_payments.created_at")
            ->whereNull('invoice_payment_id')
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->whereHas('booking', fn($booking) => $booking->where('tenant_id', $tenantId)))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->whereHas('booking', fn($booking) => $booking->where('branch_id', $branchId)));

        $paginator = DB::query()
            ->fromSub($invoicePayments->toBase()->unionAll($transactionPayments->toBase()), 'payment_history')
            ->orderByDesc(DB::raw('COALESCE(paid_at, created_at)'))
            ->orderByDesc('source_id')
            ->paginate($perPage, ['*'], 'latest_page', $page);

        $rows = $paginator->getCollection();
        $invoiceIds = $rows->where('source_type', 'invoice')->pluck('source_id')->all();
        $transactionIds = $rows->where('source_type', 'transaction')->pluck('source_id')->all();

        $invoiceModels = Payment::query()
            ->with(['invoice.bookings.customer', 'paymentAccount', 'creator'])
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy('id');
        $transactionModels = BookingPayment::query()
            ->with(['booking.customer', 'paymentAccount', 'creator'])
            ->whereIn('id', $transactionIds)
            ->get()
            ->keyBy('id');

        $paginator->setCollection($rows
            ->map(function ($row) use ($invoiceModels, $transactionModels) {
                if ($row->source_type === 'invoice') {
                    $payment = $invoiceModels->get($row->source_id);

                    return $payment ? $this->formatInvoicePaymentHistory($payment) : null;
                }

                $payment = $transactionModels->get($row->source_id);

                return $payment ? $this->formatBookingPaymentHistory($payment) : null;
            })
            ->filter()
            ->values());

        return $paginator;
    }

    private function groupedPaymentHistory(array $filters): LengthAwarePaginator
    {
        $perPage = $this->historyPerPage($filters['group_per_page'] ?? $filters['group_limit'] ?? 15);
        $page = $this->historyPage($filters['group_page'] ?? 1);

        $paginator = BookingPayment::query()
            ->select('booking_id')
            ->selectRaw('SUM(CASE WHEN COALESCE(status, ?) != ? THEN amount ELSE 0 END) as total_amount', ['active', 'voided'])
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('MAX(paid_at) as latest_paid_at')
            ->with(['booking.customer'])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->whereHas('booking', fn($booking) => $booking->where('tenant_id', $tenantId)))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->whereHas('booking', fn($booking) => $booking->where('branch_id', $branchId)))
            ->groupBy('booking_id')
            ->orderByDesc('latest_paid_at')
            ->paginate($perPage, ['*'], 'group_page', $page);

        $bookingIds = $paginator->getCollection()->pluck('booking_id')->filter()->all();
        $paymentsByBooking = BookingPayment::query()
            ->with(['booking.customer', 'paymentAccount', 'creator', 'invoicePayment.invoice', 'invoicePayment.creator'])
            ->whereIn('booking_id', $bookingIds)
            ->latest('paid_at')
            ->get()
            ->groupBy('booking_id');

        $paginator->setCollection($paginator->getCollection()
            ->map(function (BookingPayment $summary) use ($paymentsByBooking) {
                $payments = $paymentsByBooking->get($summary->booking_id, collect());
                $booking = $summary->booking;

                return [
                    'booking_id' => $booking?->id,
                    'kode_booking' => $booking?->kode_booking,
                    'customer_name' => $booking?->customer?->nama,
                    'total_amount' => (int) $summary->total_amount,
                    'payment_count' => (int) $summary->payment_count,
                    'latest_paid_at' => $summary->latest_paid_at ? Carbon::parse($summary->latest_paid_at)->toISOString() : null,
                    'payments' => $payments
                        ->sortByDesc(fn(BookingPayment $payment) => $payment->paid_at?->timestamp ?? 0)
                        ->values()
                        ->map(fn(BookingPayment $payment) => $this->formatBookingPaymentHistory($payment))
                        ->values(),
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

    private function paginatorMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function emptyPaginatorMeta(int $perPage): array
    {
        return [
            'total' => 0,
            'per_page' => $this->historyPerPage($perPage),
            'current_page' => 1,
            'last_page' => 1,
        ];
    }

    public function generateInvoice(array $bookingIds, int $branchId, int $tenantId, ?string $dueDate = null): Invoice
    {
        return DB::transaction(function () use ($bookingIds, $branchId, $tenantId, $dueDate) {
            $bookings = Booking::query()
                ->with(['customer.member', 'bookingDetails.costs', 'payments', 'invoices'])
                ->whereIn('id', $bookingIds)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->get();

            if ($bookings->count() !== count(array_unique($bookingIds))) {
                throw new \InvalidArgumentException('Sebagian booking tidak ditemukan pada branch aktif.');
            }

            $activeInvoiceBooking = $bookings->first(
                fn(Booking $booking) =>
                $booking->invoices->contains(
                    fn(Invoice $invoice) =>
                    ! in_array($invoice->status, ['void', 'paid'], true) && $this->invoiceRemaining($invoice) > 0
                )
            );

            if ($activeInvoiceBooking) {
                throw new \InvalidArgumentException("Booking {$activeInvoiceBooking->kode_booking} sudah memiliki invoice aktif.");
            }

            $amounts = $bookings->mapWithKeys(fn(Booking $booking) => [
                $booking->id => $this->billingService->totalTagihan($booking),
            ]);

            if ($amounts->contains(fn($amount) => $amount <= 0)) {
                throw new \InvalidArgumentException('Semua booking harus memiliki tagihan.');
            }

            $paidAmount = (int) $bookings->sum(fn(Booking $booking) => $this->directBookingPayments($booking));

            $invoiceDueDate = $dueDate
                ? Carbon::parse($dueDate)
                : $this->defaultInvoiceDueDate($bookings);

            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'invoice_number' => $this->nextInvoiceNumber($branchId),
                'public_token' => $this->newPublicToken(),
                'status' => 'generated',
                'total_amount' => (int) $amounts->sum(),
                'paid_amount' => $paidAmount,
                'due_date' => $invoiceDueDate,
                'generated_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($amounts as $bookingId => $amount) {
                $invoice->bookings()->attach($bookingId, ['amount' => (int) $amount]);
            }

            $invoice->update(['status' => $this->invoiceStatus((int) $invoice->total_amount, $paidAmount)]);

            return $invoice->load(['bookings.customer', 'payments.paymentAccount', 'payments.bookingPayments']);
        });
    }

    public function markSent(Invoice $invoice): Invoice
    {
        $invoice->update([
            'public_token' => $invoice->public_token ?: $this->newPublicToken(),
            'sent_at' => now(),
            'sent_by' => auth()->id(),
        ]);

        return $invoice->fresh(['bookings.customer', 'payments.paymentAccount', 'creator', 'sentBy']);
    }

    public function storePayment(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::query()
                ->with(['bookings.bookingDetails.costs', 'bookings.payments.paymentAccount', 'payments.bookingPayments'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw new \InvalidArgumentException('Invoice void tidak bisa dibayar.');
            }

            $remaining = max(0, (int) $invoice->total_amount - $this->invoicePaidAmount($invoice));
            if ((int) $data['amount'] > $remaining) {
                throw new \InvalidArgumentException('Nominal pembayaran melebihi sisa invoice.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_account_id' => $data['payment_account_id'],
                'amount' => (int) $data['amount'],
                'paid_at' => \App\Helpers\DateHelper::parseDateWithCurrentTime($data['paid_at'] ?? null),
                'created_by' => auth()->id(),
            ]);

            $account = PaymentAccount::lockForUpdate()->findOrFail($payment->payment_account_id);
            $this->transactionService->applyDelta($account, (int) $payment->amount, [
                'type'           => 'invoice_payment_in',
                'amount'         => (int) $payment->amount,
                'description'    => "Pembayaran piutang invoice #{$invoice->invoice_number}",
                'created_by'     => auth()->id(),
                'transaction_at' => $payment->paid_at ?? now(),
            ]);

            $this->syncInvoicePaymentToBookings($invoice, $payment);

            $invoice->refresh();
            $invoice->load(['bookings.payments.paymentAccount', 'payments.bookingPayments']);
            $paidAmount = $this->invoicePaidAmount($invoice);
            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $this->invoiceStatus((int) $invoice->total_amount, $paidAmount),
            ]);

            return $invoice->fresh(['bookings.customer', 'payments.paymentAccount', 'payments.bookingPayments']);
        });
    }

    public function publicInvoice(string $token): array
    {
        $invoice = Invoice::query()
            ->with(['branch', 'bookings.customer', 'bookings.bookingDetails.unit', 'payments.paymentAccount', 'payments.bookingPayments'])
            ->where('public_token', $token)
            ->where('status', '!=', 'void')
            ->firstOrFail();

        $paymentAccounts = PaymentAccount::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('is_active', true)
            ->orderBy('nama_bank')
            ->get();

        return [$invoice, $paymentAccounts];
    }

    private function syncInvoicePaymentToBookings(Invoice $invoice, Payment $payment): void
    {
        $remainingPayment = (int) $payment->amount;
        $previousPaymentIds = $invoice->payments()
            ->whereKeyNot($payment->id)
            ->pluck('id');

        foreach ($invoice->bookings as $booking) {
            if ($remainingPayment <= 0) {
                break;
            }

            $invoiceBookingAmount = (int) $booking->pivot->amount;
            $alreadyAllocated = $previousPaymentIds->isEmpty()
                ? 0
                : (int) BookingPayment::query()
                    ->where('booking_id', $booking->id)
                    ->whereIn('invoice_payment_id', $previousPaymentIds)
                    ->sum('amount');
            $alreadyAllocated += $this->directBookingPayments($booking);

            $bookingAllocationDue = max(0, $invoiceBookingAmount - $alreadyAllocated);
            $allocated = min($remainingPayment, $bookingAllocationDue);

            if ($allocated <= 0) {
                continue;
            }

            $bookingRemaining = $this->billingService->sisaTagihan($booking);

            BookingPayment::create([
                'booking_id' => $booking->id,
                'payment_account_id' => $payment->payment_account_id,
                'amount' => $allocated,
                'payment_type' => $allocated >= $bookingRemaining ? 'pelunasan' : 'cicilan',
                'status' => 'active',
                'catatan' => "Pembayaran invoice {$invoice->invoice_number}",
                'paid_at' => $payment->paid_at,
                'invoice_payment_id' => $payment->id,
                'created_by' => $payment->created_by,
            ]);

            $booking->unsetRelation('payments');
            $booking->load('bookingDetails.costs', 'payments');
            $this->billingService->updateCachedSisaTagihan($booking);

            $remainingPayment -= $allocated;
        }
    }

    private function formatInvoicePaymentHistory(Payment $payment): array
    {
        return [
            'id' => 'invoice-' . $payment->id,
            'source' => 'invoice',
            'source_label' => 'Pembayaran Invoice',
            'reference_number' => $payment->invoice?->invoice_number,
            'invoice_number' => $payment->invoice?->invoice_number,
            'transaction_codes' => $payment->invoice?->bookings?->pluck('kode_booking')->filter()->values() ?? collect(),
            'customer_names' => $payment->invoice?->bookings?->pluck('customer.nama')->filter()->unique()->values() ?? collect(),
            'payment_account_name' => $payment->paymentAccount
                ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                : null,
            'created_by_name' => $payment->creator?->name,
            'amount' => (int) $payment->amount,
            'status' => 'active',
            'payment_type' => null,
            'note' => null,
            'paid_at' => $payment->paid_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    private function formatBookingPaymentHistory(BookingPayment $payment): array
    {
        $invoiceNumber = $payment->invoicePayment?->invoice?->invoice_number;

        return [
            'id' => 'transaction-' . $payment->id,
            'source' => $invoiceNumber ? 'invoice_allocation' : 'transaction',
            'source_label' => $invoiceNumber ? 'Alokasi Invoice' : 'Pembayaran Transaksi',
            'reference_number' => $invoiceNumber ?: $payment->booking?->kode_booking,
            'invoice_number' => $invoiceNumber,
            'transaction_codes' => collect([$payment->booking?->kode_booking])->filter()->values(),
            'customer_names' => collect([$payment->booking?->customer?->nama])->filter()->values(),
            'payment_account_name' => $payment->paymentAccount
                ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                : null,
            'created_by_name' => $payment->creator?->name ?? $payment->invoicePayment?->creator?->name,
            'amount' => (int) $payment->amount,
            'status' => $payment->status ?? 'active',
            'payment_type' => $payment->payment_type,
            'note' => $payment->catatan,
            'paid_at' => $payment->paid_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ];
    }

    private function invoiceRemaining(Invoice $invoice): int
    {
        return max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount);
    }

    public function invoicePaymentHistory(Invoice $invoice)
    {
        $invoice->loadMissing(['bookings.payments.paymentAccount', 'payments.paymentAccount']);

        $invoicePayments = collect($invoice->payments->map(fn(Payment $payment) => [
            'id' => 'invoice-' . $payment->id,
            'source' => 'invoice',
            'payment_account_id' => $payment->payment_account_id,
            'payment_account_name' => $payment->paymentAccount
                ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                : null,
            'amount' => (int) $payment->amount,
            'paid_at' => $payment->paid_at?->toISOString(),
            'created_at' => $payment->created_at?->toISOString(),
        ])->all());

        $directPayments = collect($invoice->bookings
            ->flatMap(fn(Booking $booking) => $booking->payments)
            ->filter(
                fn(BookingPayment $payment) => ($payment->status ?? 'active') !== 'voided'
                    && $payment->invoice_payment_id === null
            )
            ->map(fn(BookingPayment $payment) => [
                'id' => 'booking-' . $payment->id,
                'source' => 'booking',
                'payment_account_id' => $payment->payment_account_id,
                'payment_account_name' => $payment->paymentAccount
                    ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                    : null,
                'amount' => (int) $payment->amount,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ])
            ->all());

        return $invoicePayments
            ->merge($directPayments)
            ->sortByDesc(fn($payment) => $payment['paid_at'] ?? $payment['created_at'])
            ->values();
    }

    public function invoiceItems(Invoice $invoice)
    {
        $invoice->loadMissing(['bookings.bookingDetails.unit', 'bookings.bookingDetails.costs']);

        return $invoice->bookings
            ->flatMap(function (Booking $booking) {
                return $booking->bookingDetails
                    ->whereNotIn('status', ['batal'])
                    ->flatMap(function ($detail) use ($booking) {
                        $unit = $detail->unit;
                        $vehicleName = trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: ($detail->unit_placeholder ?? null);
                        $detailAmount = $this->detailBaseAmount($detail);
                        $items = collect();

                        if ($detailAmount !== 0) {
                            $items->push([
                                'type' => $detail->detail_type === 'extend' ? 'extend' : 'rental',
                                'description' => $detail->detail_type === 'extend' ? 'Extend' : $booking->kode_booking,
                                'booking_code' => $booking->kode_booking,
                                'vehicle_name' => $vehicleName,
                                'vehicle_plate' => $unit?->no_polisi,
                                'rental_start_date' => $detail->tgl_sewa,
                                'rental_end_date' => $detail->tgl_kembali,
                                'price' => (int) $detailAmount,
                                'qty' => 1,
                                'amount' => (int) $detailAmount,
                            ]);
                        }

                        $detail->costs
                            ->where('is_additional', true)
                            ->each(function ($cost) use ($items, $booking, $detail, $unit, $vehicleName) {
                                $amount = $cost->type === 'diskon' ? - ((int) $cost->amount) : (int) $cost->amount;
                                $items->push([
                                    'type' => 'additional_cost',
                                    'description' => 'Biaya Tambahan',
                                    'booking_code' => $booking->kode_booking,
                                    'label' => $cost->label,
                                    'note' => $cost->keterangan,
                                    'vehicle_name' => $vehicleName,
                                    'vehicle_plate' => $unit?->no_polisi,
                                    'rental_start_date' => $detail->tgl_sewa,
                                    'rental_end_date' => $detail->tgl_kembali,
                                    'price' => $amount,
                                    'qty' => 1,
                                    'amount' => $amount,
                                ]);
                            });

                        return $items;
                    });
            })
            ->values();
    }

    private function currentInvoiceBookingAmounts(Invoice $invoice)
    {
        $invoice->loadMissing(['bookings.bookingDetails.costs', 'bookings.payments.paymentAccount', 'payments.bookingPayments']);

        return $invoice->bookings->mapWithKeys(function (Booking $booking) use ($invoice) {
            return [
                $booking->id => $this->billingService->totalTagihan($booking),
            ];
        });
    }

    public function invoicePaidAmount(Invoice $invoice): int
    {
        $invoice->loadMissing(['bookings.payments', 'payments']);

        return (int) $invoice->payments->sum('amount')
            + (int) $invoice->bookings->sum(fn(Booking $booking) => $this->directBookingPayments($booking));
    }

    private function directBookingPayments(Booking $booking): int
    {
        if (! $booking->relationLoaded('payments')) {
            $booking->load('payments');
        }

        return (int) $booking->payments
            ->filter(
                fn(BookingPayment $payment) => ($payment->status ?? 'active') !== 'voided'
                    && $payment->invoice_payment_id === null
            )
            ->sum('amount');
    }

    private function invoiceStatus(int $totalAmount, int $paidAmount): string
    {
        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return 'paid';
        }

        return $paidAmount > 0 ? 'partial_paid' : 'generated';
    }

    private function detailBaseAmount($detail): int
    {
        $duration = $detail->lama_sewa ?? 1;
        $costs = $detail->relationLoaded('costs') ? $detail->costs : collect();
        $regularCosts = $costs
            ->where('is_additional', false)
            ->sum(fn($cost) => $cost->type === 'diskon' ? - ((int) $cost->amount) : (int) $cost->amount);

        if ($detail->pricing_mode === 'all_in') {
            $regularCosts = $costs
                ->where('is_additional', false)
                ->where('type', 'diskon')
                ->sum(fn($cost) => - ((int) $cost->amount));

            return ((int) ($detail->harga_all_in ?? 0) * $duration) + $regularCosts;
        }

        return (((int) $detail->harga_mobil - (int) $detail->diskon_mobil) * $duration) + $regularCosts;
    }

    private function nextInvoiceNumber(int $branchId): string
    {
        $prefix = 'INV-' . date('Ym') . '-' . str_pad((string) $branchId, 2, '0', STR_PAD_LEFT) . '-';
        $lastInvoice = Invoice::where('branch_id', $branchId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->first();

        $next = $lastInvoice ? ((int) substr($lastInvoice->invoice_number, -5)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function newPublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Invoice::where('public_token', $token)->exists());

        return $token;
    }

    private function defaultInvoiceDueDate($bookings): ?Carbon
    {
        return $bookings
            ->map(function (Booking $booking) {
                if (! $booking->due_date) {
                    $booking->update([
                        'due_date' => $this->billingService->calculateDueDate($booking),
                    ]);
                    $booking->refresh();
                }

                return $booking->due_date ? Carbon::parse($booking->due_date) : null;
            })
            ->filter()
            ->sortDesc()
            ->first();
    }
}
