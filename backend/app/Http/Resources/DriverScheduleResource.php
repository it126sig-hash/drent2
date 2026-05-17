<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'tgl_sewa' => $this->tgl_sewa,
            'tgl_kembali' => $this->tgl_kembali,
            'status' => $this->status,
            'pricing_mode' => $this->pricing_mode,
            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id' => $this->booking->id,
                'kode_booking' => $this->booking->kode_booking,
                'status' => $this->booking->status,
                'tujuan' => $this->booking->tujuan,
                'kota' => $this->booking->kota,
                'customer' => $this->booking->relationLoaded('customer') && $this->booking->customer ? [
                    'id' => $this->booking->customer->id,
                    'nama' => $this->booking->customer->nama,
                ] : null,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'no_polisi' => $this->unit->no_polisi,
                'merk' => $this->unit->merk,
                'tipe' => $this->unit->tipe,
            ] : null),
        ];
    }
}
