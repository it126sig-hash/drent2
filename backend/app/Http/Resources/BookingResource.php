<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'harga_dealing' => (int) $this->harga_dealing,
            'dp' => (int) $this->dp,
            'rekening_dp_id' => $this->rekening_dp_id,
            'tujuan' => $this->tujuan,
            'alamat_penjemputan' => $this->alamat_penjemputan,
            'catatan' => $this->catatan,
            'booking_details' => BookingDetailResource::collection($this->whenLoaded('bookingDetails')),
            'created_at' => $this->created_at,
        ];
    }
}
