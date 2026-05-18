<?php

namespace App\Http\Resources;

use App\Services\RentToRentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRentToRentBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(RentToRentService::class);
        $paidAmount = $service->billPaidAmount($this->resource);

        return [
            'bill_number' => $this->bill_number,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, (int) $this->total_amount - $paidAmount),
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name ?? $this->branch->nama ?? null,
                'address' => $this->branch->address ?? null,
                'phone' => $this->branch->phone ?? null,
            ] : null,
            'rental_owner' => [
                'id' => $this->rentalOwner?->id,
                'nama' => $this->rentalOwner?->nama,
                'kontak_1' => $this->rentalOwner?->kontak_1,
                'kontak_2' => $this->rentalOwner?->kontak_2,
                'alamat' => $this->rentalOwner?->alamat,
                'kota' => $this->rentalOwner?->kota,
                'bank' => $this->rentalOwner?->bank,
                'no_rek' => $this->rentalOwner?->no_rek,
                'atas_nama' => $this->rentalOwner?->atas_nama,
            ],
            'items' => $this->whenLoaded('items', fn() => $this->items->map(function ($item) {
                $debt = $item->debt;
                $detail = $debt?->bookingDetail;
                $unit = $detail?->unit;

                return [
                    'kode_booking' => $debt?->booking?->kode_booking,
                    'customer_name' => $debt?->booking?->customer?->nama,
                    'tujuan' => $debt?->booking?->tujuan,
                    'unit_name' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: '-',
                    'unit_plate' => $unit?->no_polisi,
                    'rental_start_date' => $detail?->tgl_sewa,
                    'rental_end_date' => $detail?->tgl_kembali,
                    'package' => $detail?->paket_sewa,
                    'duration' => $detail?->lama_sewa,
                    'amount' => (int) $item->amount,
                    'paid_amount' => (int) $item->allocations
                        ->filter(fn($allocation) => ($allocation->payment?->status ?? 'active') !== 'voided')
                        ->sum('amount'),
                ];
            })->values()),
            'payments' => $this->whenLoaded('payments', fn() => $this->payments
                ->sortByDesc(fn($payment) => $payment->paid_at?->timestamp ?? 0)
                ->map(fn($payment) => [
                    'id' => $payment->id,
                    'status' => $payment->status ?? 'active',
                    'payment_account_name' => $payment->paymentAccount
                        ? trim($payment->paymentAccount->nama_bank.' '.$payment->paymentAccount->nomor_rekening)
                        : null,
                    'amount' => (int) $payment->amount,
                    'paid_at' => $payment->paid_at?->toISOString(),
                ])
                ->values()),
        ];
    }
}
