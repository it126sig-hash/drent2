<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailResource extends JsonResource
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
            'unit_id' => $this->unit_id,
            'unit_placeholder' => $this->unit_placeholder,
            'driver_id' => $this->driver_id,
            'tgl_sewa' => $this->tgl_sewa,
            'tgl_kembali' => $this->tgl_kembali,
            'harga_mobil' => (int) $this->harga_mobil,
            'diskon_mobil' => (int) $this->diskon_mobil,
            'detail_type' => $this->detail_type,
            'status' => $this->status,
        ];
    }
}
