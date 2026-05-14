<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => (int) $this->paid_amount,
            'remaining_amount' => max(0, (int) $this->total_amount - (int) $this->paid_amount),
            'due_date' => $this->due_date?->toISOString(),
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'pdf_url' => url("/api/v1/invoices/{$this->id}/pdf"),
            'bookings' => $this->whenLoaded('bookings', fn() => $this->bookings->map(fn($booking) => [
                'id' => $booking->id,
                'kode_booking' => $booking->kode_booking,
                'customer_name' => $booking->customer?->nama,
                'amount' => (int) $booking->pivot->amount,
            ])),
            'payments' => $this->whenLoaded('payments', fn() => $this->payments->map(fn($payment) => [
                'id' => $payment->id,
                'payment_account_id' => $payment->payment_account_id,
                'payment_account_name' => $payment->paymentAccount
                    ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                    : null,
                'amount' => (int) $payment->amount,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ])),
        ];
    }
}
