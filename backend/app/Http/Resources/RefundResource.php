<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'booking_id'         => $this->booking_id,
            'payment_account_id' => $this->payment_account_id,
            'amount'             => (int) $this->amount,
            'keterangan'         => $this->keterangan,
            'refunded_at'        => $this->refunded_at?->toISOString(),
            'created_by'         => $this->created_by,
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
            'payment_account'    => $this->whenLoaded('paymentAccount', fn() => [
                'id'        => $this->paymentAccount->id,
                'nama_bank' => $this->paymentAccount->nama_bank,
                'nomor_rekening' => $this->paymentAccount->nomor_rekening,
                'atas_nama' => $this->paymentAccount->atas_nama,
            ]),
            'creator'            => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
