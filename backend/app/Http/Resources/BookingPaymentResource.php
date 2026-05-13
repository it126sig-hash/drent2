<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'booking_id'            => $this->booking_id,
            'payment_account_id'    => $this->payment_account_id,
            'amount'                => (int) $this->amount,
            'payment_type'          => $this->payment_type,
            'catatan'               => $this->catatan,
            'paid_at'               => $this->paid_at?->toISOString(),
            'reallocated_from_id'   => $this->reallocated_from_id,
            'created_by'            => $this->created_by,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
            'payment_account'       => $this->whenLoaded('paymentAccount', fn() => [
                'id'        => $this->paymentAccount->id,
                'nama_bank' => $this->paymentAccount->nama_bank,
                'nomor_rekening' => $this->paymentAccount->nomor_rekening,
                'atas_nama' => $this->paymentAccount->atas_nama,
            ]),
            'creator'               => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'reallocated_from'      => $this->whenLoaded('reallocatedFrom', fn() => $this->reallocatedFrom ? [
                'id'           => $this->reallocatedFrom->id,
                'booking_id'   => $this->reallocatedFrom->booking_id,
                'amount'       => (int) $this->reallocatedFrom->amount,
                'payment_type' => $this->reallocatedFrom->payment_type,
            ] : null),
        ];
    }
}
