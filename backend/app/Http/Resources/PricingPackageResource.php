<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'tenant_id'   => $this->tenant_id,
            'branch_id'   => $this->branch_id,
            'cost_type_id' => $this->cost_type_id,
            'cost_type'   => $this->whenLoaded('costType', fn () => $this->costType ? [
                'id'                  => $this->costType->id,
                'nama'                => $this->costType->nama,
                'kode'                => $this->costType->kode,
                'require_description' => (bool) $this->costType->require_description,
                'is_active'           => (bool) $this->costType->is_active,
                'sort_order'          => (int) $this->costType->sort_order,
            ] : null),
            'items'       => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'           => $item->id,
                'cost_type_id' => $item->cost_type_id,
                'cost_type'    => $item->relationLoaded('costType') && $item->costType ? [
                    'id'                  => $item->costType->id,
                    'nama'                => $item->costType->nama,
                    'kode'                => $item->costType->kode,
                    'require_description' => (bool) $item->costType->require_description,
                    'is_active'           => (bool) $item->costType->is_active,
                    'sort_order'          => (int) $item->costType->sort_order,
                ] : null,
                'type'         => $item->type,
                'label'        => $item->label,
                'amount'       => (int) $item->amount,
                'keterangan'   => $item->keterangan,
                'sort_order'   => (int) $item->sort_order,
            ])),
            'nama_paket'  => $this->nama_paket,
            'kota_asal'   => $this->kota_asal,
            'kota_tujuan' => $this->kota_tujuan,
            'harga'       => (int) $this->harga,
            'keterangan'  => $this->keterangan,
            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
