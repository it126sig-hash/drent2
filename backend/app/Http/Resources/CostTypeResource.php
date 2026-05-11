<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tenant_id'           => $this->tenant_id,
            'nama'                => $this->nama,
            'kode'                => $this->kode,
            'require_description' => (bool) $this->require_description,
            'is_active'           => (bool) $this->is_active,
            'sort_order'          => (int) $this->sort_order,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
