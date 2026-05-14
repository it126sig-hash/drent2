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

class ReceivableService
{
    public function __construct(private BookingBillingService $billingService)
    {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        $bookings = Booking::query()
            ->with([
                'customer.member',
                'bookingDetails.unit.rentalOwner',
                'bookingDetails.costs',
                'payments',
                'invoices' => fn($query) => $query->where('status', '!=', 'void')->latest('generated_at'),
            ])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->whereNotIn('status', ['batal'])
            ->when($filters['invoice_status'] ?? null, function ($query, $status) {
                if ($status === 'generated') {
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
            ->get()
            ->map(function (Booking $booking) {
                $booking->total_tagihan = $this->billingService->totalTagihan($booking);
                $booking->total_payments = $this->billingService->paidAmount($booking);
                $booking->sisa_tagihan = $this->billingService->sisaTagihan($booking);
                $booking->latest_active_invoice = $booking->invoices
                    ->first(fn(Invoice $invoice) => $invoice->status !== 'paid' && $this->invoiceRemaining($invoice) > 0);
                $booking->display_detail = $booking->bookingDetails->firstWhere('status', 'aktif')
                    ?? $booking->bookingDetails->firstWhere('detail_type', 'initial')
                    ?? $booking->bookingDetails->first();

                return $booking;
            })
            ->filter(fn(Booking $booking) => $booking->sisa_tagihan > 0)
            ->values();

        return new LengthAwarePaginator(
            $bookings->forPage($page, $perPage)->values(),
            $bookings->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function invoices(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Invoice::query()
            ->with(['bookings.customer', 'payments.paymentAccount'])
            ->when($filters['tenant_id'] ?? null, fn($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($filters['branch_id'] ?? null, fn($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->latest('generated_at')
            ->paginate($perPage);
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

            $activeInvoiceBooking = $bookings->first(fn(Booking $booking) =>
                $booking->invoices->contains(fn(Invoice $invoice) =>
                    ! in_array($invoice->status, ['void', 'paid'], true) && $this->invoiceRemaining($invoice) > 0
                )
            );

            if ($activeInvoiceBooking) {
                throw new \InvalidArgumentException("Booking {$activeInvoiceBooking->kode_booking} sudah memiliki invoice aktif.");
            }

            $amounts = $bookings->mapWithKeys(fn(Booking $booking) => [
                $booking->id => $this->billingService->sisaTagihan($booking),
            ]);

            if ($amounts->contains(fn($amount) => $amount <= 0)) {
                throw new \InvalidArgumentException('Semua booking harus memiliki sisa tagihan.');
            }

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
                'paid_amount' => 0,
                'due_date' => $invoiceDueDate,
                'generated_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($amounts as $bookingId => $amount) {
                $invoice->bookings()->attach($bookingId, ['amount' => (int) $amount]);
            }

            return $invoice->load(['bookings.customer', 'payments.paymentAccount']);
        });
    }

    public function markSent(Invoice $invoice): Invoice
    {
        $invoice->update([
            'public_token' => $invoice->public_token ?: $this->newPublicToken(),
            'sent_at' => now(),
        ]);

        return $invoice->fresh(['bookings.customer', 'payments.paymentAccount']);
    }

    public function storePayment(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = Invoice::query()
                ->with(['bookings.bookingDetails.costs', 'bookings.payments', 'payments'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status === 'void') {
                throw new \InvalidArgumentException('Invoice void tidak bisa dibayar.');
            }

            $remaining = max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount);
            if ((int) $data['amount'] > $remaining) {
                throw new \InvalidArgumentException('Nominal pembayaran melebihi sisa invoice.');
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_account_id' => $data['payment_account_id'],
                'amount' => (int) $data['amount'],
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'created_by' => auth()->id(),
            ]);

            $this->syncInvoicePaymentToBookings($invoice, $payment);

            $invoice->refresh();
            $paidAmount = (int) $invoice->payments()->sum('amount');
            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= (int) $invoice->total_amount ? 'paid' : 'partial_paid',
            ]);

            return $invoice->fresh(['bookings.customer', 'payments.paymentAccount']);
        });
    }

    public function publicInvoice(string $token): array
    {
        $invoice = Invoice::query()
            ->with(['branch', 'bookings.customer', 'payments.paymentAccount'])
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

            $remainingPayment -= $allocated;
        }
    }

    private function invoiceRemaining(Invoice $invoice): int
    {
        return max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount);
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
