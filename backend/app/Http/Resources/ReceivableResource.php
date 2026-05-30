<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->latest_active_invoice;
        $paidInvoice = $this->latest_paid_invoice;
        $detail = $this->display_detail;
        $unit = $detail?->unit;
        $receivableService = app(\App\Services\ReceivableService::class);
        $invoiceReconciliation = $invoice
            ? ($this->invoice_reconciliation ?? $receivableService->invoiceReconciliation($invoice))
            : null;
        $paidInvoiceReconciliation = $this->paid_invoice_reconciliation ?? null;

        return [
            'id' => $this->id,
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'due_date' => $this->due_date?->toISOString(),
            'kota' => $this->kota,
            'rent_period' => [
                'tujuan' => $this->tujuan,
                'tgl_sewa' => $detail?->tgl_sewa instanceof \Carbon\Carbon ? $detail->tgl_sewa->toIso8601String() : $detail?->tgl_sewa,
                'tgl_kembali' => $detail?->tgl_kembali instanceof \Carbon\Carbon ? $detail->tgl_kembali->toIso8601String() : $detail?->tgl_kembali,
                'paket_sewa' => $detail?->paket_sewa ?: $this->paket_sewa ?: '-',
            ],
            'customer' => [
                'id' => $this->customer?->id,
                'nama' => $this->customer?->nama,
                'status' => $this->customer?->status,
            ],
            'vehicle' => [
                'jenis' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: ($detail?->unit_placeholder ?? '-'),
                'no_polisi' => $unit?->no_polisi,
                'pemilik' => $unit?->rentalOwner?->nama ?? 'Internal',
            ],
            'total_biaya' => [
                'total' => (int) $this->total_tagihan,
                'sudah_bayar' => (int) $this->total_payments,
                'sisa' => (int) $this->sisa_tagihan,
            ],
            'invoice' => [
                'generated' => (bool) $invoice,
                'id' => $invoice?->id,
                'number' => $invoice?->invoice_number,
                'status' => $invoice?->status,
                'total_amount' => $invoice ? (int) $invoice->total_amount : null,
                'paid_amount' => $invoice ? (int) $invoice->paid_amount : null,
                'remaining_amount' => $invoice ? max(0, (int) $invoice->total_amount - (int) $invoice->paid_amount) : null,
                'due_date' => $invoice?->due_date?->toISOString(),
                'generated_at' => $invoice?->generated_at?->toISOString(),
                'sent_at' => $invoice?->sent_at?->toISOString(),
                'created_by_name' => $invoice?->creator?->name,
                'sent_by_name' => $invoice?->sentBy?->name,
                'pdf_url' => $invoice ? url("/api/v1/invoices/{$invoice->id}/pdf") : null,
                'public_path' => $invoice?->public_token ? "/invoice/{$invoice->public_token}" : null,
                'public_url' => $invoice?->public_token ? config('app.frontend_url', url('/')) . "/invoice/{$invoice->public_token}" : null,
                'invoice_reconciliation' => $invoiceReconciliation,
                'items' => $invoice ? $receivableService->invoiceItems($invoice) : [],
                'payments' => $invoice ? $receivableService->invoicePaymentHistory($invoice) : [],
            ],
            'paid_invoice_with_delta' => ($paidInvoiceReconciliation['is_changed'] ?? false)
                && ($paidInvoiceReconciliation['difference_amount'] ?? 0) > 0
                ? [
                    'id'             => $paidInvoice->id,
                    'number'         => $paidInvoice->invoice_number,
                    'status'         => $paidInvoice->status,
                    'reconciliation' => $paidInvoiceReconciliation,
                    'public_path'    => $paidInvoice->public_token ? "/invoice/{$paidInvoice->public_token}" : null,
                    'sent_at'        => $paidInvoice->sent_at?->toISOString(),
                ]
                : null,
        ];
    }
}
