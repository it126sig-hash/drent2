<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receivableService = app(\App\Services\ReceivableService::class);
        $paidAmount = $receivableService->invoicePaidAmount($this->resource);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, (int) $this->total_amount - $paidAmount),
            'due_date' => $this->due_date?->toISOString(),
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'created_by_name' => $this->creator?->name,
            'sent_by_name' => $this->sentBy?->name,
            'invoice_reconciliation' => $receivableService->invoiceReconciliation($this->resource),
            'pdf_url' => url("/api/v1/invoices/{$this->id}/pdf"),
            'public_path' => $this->public_token ? "/invoice/{$this->public_token}" : null,
            'public_url' => $this->public_token ? config('app.frontend_url', url('/')) . "/invoice/{$this->public_token}" : null,
            'bookings' => $this->whenLoaded('bookings', fn() => $this->bookings->map(fn($booking) => [
                'id' => $booking->id,
                'kode_booking' => $booking->kode_booking,
                'customer_name' => $booking->customer?->nama,
                'amount' => (int) $booking->pivot->amount,
            ])),
            'items' => $this->whenLoaded('bookings', fn() => $receivableService->invoiceItems($this->resource)),
            'payments' => $this->whenLoaded('payments', fn() => $receivableService->invoicePaymentHistory($this->resource)),
        ];
    }
}
