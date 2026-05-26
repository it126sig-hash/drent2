<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'tenant_id'       => $this->tenant_id,
            'branch_id'       => $this->branch_id,
            'nama_bank'       => $this->nama_bank,
            'nomor_rekening'  => $this->nomor_rekening,
            'atas_nama'       => $this->atas_nama,
            'current_balance' => (int) $this->current_balance,
            'is_active'       => (bool) $this->is_active,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
