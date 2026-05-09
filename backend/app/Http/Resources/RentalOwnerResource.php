<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalOwnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'kontak_1' => $this->kontak_1,
            'kontak_2' => $this->kontak_2,
            'alamat' => $this->alamat,
            'kota' => $this->kota,
            'bank' => $this->bank,
            'no_rek' => $this->no_rek,
            'atas_nama' => $this->atas_nama,
            'is_owner' => $this->is_owner,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at,
        ];
    }
}
