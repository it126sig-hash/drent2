<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvoiceResource extends JsonResource
{
    public function __construct($resource, private $paymentAccounts)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => (int) $this->paid_amount,
            'remaining_amount' => max(0, (int) $this->total_amount - (int) $this->paid_amount),
            'due_date' => $this->due_date?->toISOString(),
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name ?? $this->branch->nama ?? null,
            ] : null,
            'bookings' => $this->whenLoaded('bookings', fn() => $this->bookings->map(fn($booking) => [
                'kode_booking' => $booking->kode_booking,
                'customer_name' => $booking->customer?->nama,
                'amount' => (int) $booking->pivot->amount,
                'status' => $booking->status,
            ])),
            'payments' => $this->whenLoaded('payments', fn() => $this->payments->map(fn($payment) => [
                'payment_account_name' => $payment->paymentAccount
                    ? trim($payment->paymentAccount->nama_bank . ' ' . $payment->paymentAccount->nomor_rekening)
                    : null,
                'amount' => (int) $payment->amount,
                'paid_at' => $payment->paid_at?->toISOString(),
            ])),
            'payment_accounts' => $this->paymentAccounts->map(fn($account) => [
                'nama_bank' => $account->nama_bank,
                'nomor_rekening' => $account->nomor_rekening,
                'atas_nama' => $account->atas_nama,
            ])->values(),
        ];
    }
}
