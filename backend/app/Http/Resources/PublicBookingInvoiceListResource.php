<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBookingInvoiceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidAmount = app(\App\Services\ReceivableService::class)->invoicePaidAmount($this->resource);

        return [
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, (int) $this->total_amount - $paidAmount),
            'due_date' => $this->due_date?->toISOString(),
            'generated_at' => $this->generated_at?->toISOString(),
            'public_path' => $this->public_token ? "/invoice/{$this->public_token}" : null,
        ];
    }
}
