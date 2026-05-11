<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nama'             => $this->nama,
            'kontak_1'         => $this->kontak_1,
            'kontak_2'         => $this->kontak_2,
            'alamat'           => $this->alamat,
            'kota'             => $this->kota,
            'status'           => $this->status,
            'has_apply_member' => $this->has_apply_member,
            'catatan'          => $this->catatan,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
