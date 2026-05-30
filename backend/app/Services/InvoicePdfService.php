<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class InvoicePdfService
{
    public function __construct(private ReceivableService $receivableService)
    {
    }

    public function make(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'branch',
            'bookings.customer',
            'bookings.bookingDetails.unit',
            'bookings.bookingDetails.costs',
            'bookings.payments.paymentAccount',
            'payments.paymentAccount',
            'payments.bookingPayments',
        ]);

        $paymentAccounts = PaymentAccount::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('branch_id', $invoice->branch_id)
            ->where('is_active', true)
            ->orderBy('nama_bank')
            ->get()
            ->filter(fn($acc) => $acc->nama_bank && strtolower($acc->nama_bank) !== 'cash')
            ->map(fn($acc) => [
                'nama_bank' => $acc->nama_bank,
                'nomor_rekening' => $acc->nomor_rekening,
                'atas_nama' => $acc->atas_nama,
            ])
            ->values()
            ->all();

        $primaryBooking = $invoice->bookings->first();
        $customer = $primaryBooking?->customer;

        $customerAddressLines = array_values(array_filter([
            $customer?->alamat,
            $customer?->kota,
        ]));

        $customerContactLines = array_values(array_filter([
            $customer?->kontak_1 ? 'Telp: ' . $customer->kontak_1 : null,
            $customer?->kontak_2 ? 'Telp 2: ' . $customer->kontak_2 : null,
            $customer?->email ? 'Email: ' . $customer->email : null,
        ]));

        $items = $this->receivableService->invoiceItems($invoice)->all();
        $payments = $this->receivableService->invoicePaymentHistory($invoice)->all();
        $paidAmount = $this->receivableService->invoicePaidAmount($invoice);

        $totalAmount = (int) $invoice->total_amount;
        $remainingAmount = max(0, $totalAmount - $paidAmount);

        $branchName = $invoice->branch?->name ?: 'DRENT';

        $invoiceDateRaw = $invoice->generated_at ?? $invoice->created_at;

        $data = [
            'invoice' => $invoice,
            'branchName' => $branchName,
            'customerName' => $customer?->nama ?: 'Pelanggan',
            'customerAddressLines' => $customerAddressLines,
            'customerContactLines' => $customerContactLines,
            'invoiceDate' => $this->formatDate($invoiceDateRaw),
            'dueDate' => $this->formatDate($invoice->due_date),
            'items' => $items,
            'payments' => $payments,
            'paymentAccounts' => $paymentAccounts,
            'paidAmount' => $paidAmount,
            'remainingAmount' => $remainingAmount,
            'statusSeverity' => $this->statusSeverity($invoice->status),
            'formatCurrency' => fn($value) => $this->formatCurrency($value),
            'formatDate' => fn($value) => $this->formatDate($value),
        ];

        $pdf = Pdf::loadView('pdf.invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        return $pdf->output();
    }

    private function formatCurrency($value): string
    {
        $amount = (int) ($value ?? 0);
        $negative = $amount < 0;
        $formatted = 'Rp ' . number_format(abs($amount), 0, ',', '.');

        return $negative ? '-' . $formatted : $formatted;
    }

    private function formatDate($value): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->translatedFormat('d M Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private function statusSeverity(?string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'partial_paid' => 'info',
            'void' => 'danger',
            default => 'warn',
        };
    }
}
