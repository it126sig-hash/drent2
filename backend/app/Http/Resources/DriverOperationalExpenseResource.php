<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverOperationalExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_operational_fund_id' => $this->driver_operational_fund_id,
            'booking_id' => $this->booking_id,
            'booking_detail_id' => $this->booking_detail_id,
            'driver_id' => $this->driver_id,
            'cost_type_id' => $this->cost_type_id,
            'type' => $this->type,
            'amount' => (int) $this->amount,
            'description' => $this->description,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_url,
            'status' => $this->status,
            'source' => $this->source,
            'submitted_by' => $this->submitted_by,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
            'cost_type' => $this->whenLoaded('costType', fn () => $this->costType ? [
                'id' => $this->costType->id,
                'nama' => $this->costType->nama,
                'kode' => $this->costType->kode,
            ] : null),
            'submitter' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
                'role' => $this->submitter->role,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
        ];
    }
}
