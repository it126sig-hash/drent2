<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingCancellationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'booking_id'         => $this->booking_id,
            'tenant_id'          => $this->tenant_id,
            'branch_id'          => $this->branch_id,
            'ada_refund'         => $this->ada_refund,
            'nominal_refund'     => $this->nominal_refund ? (int) $this->nominal_refund : null,
            'bank_refund'        => $this->bank_refund,
            'no_rek_refund'      => $this->no_rek_refund,
            'nama_rek_refund'    => $this->nama_rek_refund,
            'catatan_refund'     => $this->catatan_refund,
            'sudah_bayar_refund' => $this->sudah_bayar_refund,
            'payment_account_id' => $this->payment_account_id,
            'dibayar_at'         => $this->dibayar_at?->toISOString(),
            'created_by'         => $this->created_by,
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
            'booking'            => $this->whenLoaded('booking', fn () => [
                'id'           => $this->booking->id,
                'kode_booking' => $this->booking->kode_booking,
                'status'       => $this->booking->status,
                'customer'     => $this->when($this->booking->relationLoaded('customer'), fn () => [
                    'id'   => $this->booking->customer->id,
                    'nama' => $this->booking->customer->nama,
                ]),
            ]),
            'payment_account'    => $this->whenLoaded('paymentAccount', fn () => [
                'id'             => $this->paymentAccount->id,
                'nama_bank'      => $this->paymentAccount->nama_bank,
                'nomor_rekening' => $this->paymentAccount->nomor_rekening,
                'atas_nama'      => $this->paymentAccount->atas_nama,
            ]),
            'creator'            => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'dibayar_oleh'       => $this->whenLoaded('dibayarOleh', fn () => $this->dibayarOleh ? [
                'id'   => $this->dibayarOleh->id,
                'name' => $this->dibayarOleh->name,
            ] : null),
        ];
    }
}
