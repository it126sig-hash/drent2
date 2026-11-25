<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'label' => $this->label,
            'saldo' => (int) $this->saldo,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'active_fund' => $this->whenLoaded('activeFund', fn () => $this->activeFund ? [
                'id' => $this->activeFund->id,
                'booking_id' => $this->activeFund->booking_id,
                'booking_code' => $this->activeFund->booking?->kode_booking,
                'driver_id' => $this->activeFund->driver_id,
                'driver_name' => $this->activeFund->driver?->nama,
                'status' => $this->activeFund->status,
            ] : null),
        ];
    }
}
