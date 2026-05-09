<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'tenant_id'  => $this->tenant_id,
            'branch_id'  => $this->branch_id,
            'user_id'    => $this->user_id,
            'nama'       => $this->nama,
            'alamat'     => $this->alamat,
            'kota'       => $this->kota,
            'no_sim'     => $this->no_sim,
            'kontak_1'   => $this->kontak_1,
            'kontak_2'   => $this->kontak_2,
            'saldo'      => (int) $this->saldo,
            'status'     => $this->status,
            'is_tetap'   => (bool) $this->is_tetap,
            'catatan'    => $this->catatan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
