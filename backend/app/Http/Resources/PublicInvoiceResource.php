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
        $receivableService = app(\App\Services\ReceivableService::class);
        $paidAmount = $receivableService->invoicePaidAmount($this->resource);

        return [
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, (int) $this->total_amount - $paidAmount),
            'due_date' => $this->due_date?->toISOString(),
            'terms_and_conditions' => $this->terms_and_conditions,
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'created_by_name' => $this->creator?->name,
            'signature_url' => $this->publicStorageUrl($this->creator?->signature_path),
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name ?? $this->branch->nama ?? null,
                'address' => $this->branch->address,
                'phone' => $this->branch->phone,
                'phone_alt' => $this->branch->phone_alt,
                'email' => $this->branch->email,
                'website' => $this->branch->website,
                'logo_path' => $this->branch->logo_path,
                'logo_url' => $this->publicStorageUrl($this->branch->logo_path),
            ] : null,
            'bookings' => $this->whenLoaded('bookings', fn() => $this->bookings->map(function ($booking) {
                $detail = $booking->relationLoaded('bookingDetails')
                    ? $booking->bookingDetails->sortByDesc(fn($item) => $item->status === 'aktif')->first()
                    : null;
                $unit = $detail?->unit;

                return [
                    'kode_booking' => $booking->kode_booking,
                    'customer_name' => $booking->customer?->nama,
                    'customer_phone' => $booking->customer?->kontak_1,
                    'customer_phone_alt' => $booking->customer?->kontak_2,
                    'customer_email' => $booking->customer?->email,
                    'customer_address' => $booking->customer?->alamat,
                    'customer_city' => $booking->customer?->kota,
                    'vehicle_name' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: ($detail?->unit_placeholder ?? null),
                    'vehicle_plate' => $unit?->no_polisi,
                    'rental_start_date' => $detail?->tgl_sewa,
                    'rental_end_date' => $detail?->tgl_kembali,
                    'amount' => (int) $booking->pivot->amount,
                    'status' => $booking->status,
                ];
            })),
            'items' => $this->whenLoaded('bookings', fn() => $receivableService->invoiceItems($this->resource)),
            'payments' => $this->whenLoaded('payments', fn() => $receivableService->invoicePaymentHistory($this->resource)),
            'payment_accounts' => $this->paymentAccounts->map(fn($account) => [
                'nama_bank' => $account->nama_bank,
                'nomor_rekening' => $account->nomor_rekening,
                'atas_nama' => $account->atas_nama,
            ])->values(),
        ];
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $baseUrl = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');

        return $baseUrl . '/storage/' . ltrim($path, '/');
    }
}
