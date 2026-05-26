<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
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
            'tenant_id'        => $this->tenant_id,
            'branch_id'        => $this->branch_id,
            'rental_owner_id'  => $this->rental_owner_id,
            'rental_owner'     => $this->whenLoaded('rentalOwner', fn() => [
                'id'   => $this->rentalOwner->id,
                'nama' => $this->rentalOwner->nama,
            ]),
            'city_id'          => $this->city_id,
            'city'             => $this->whenLoaded('city', fn() => [
                'id'   => $this->city->id,
                'nama' => $this->city->nama,
            ]),
            'tipe'             => $this->tipe,
            'merk'             => $this->merk,
            'tahun'            => $this->tahun,
            'no_polisi'        => $this->no_polisi,
            'harga_1_hari'     => $this->harga_1_hari,
            'harga_1_minggu'   => $this->harga_1_minggu,
            'harga_1_bulan'    => $this->harga_1_bulan,
            'harga_all_in'     => $this->harga_all_in,
            'harga_all_in_1_minggu' => $this->harga_all_in_1_minggu,
            'harga_all_in_1_bulan'  => $this->harga_all_in_1_bulan,
            'modal_1_hari'     => $this->modal_1_hari,
            'modal_1_minggu'   => $this->modal_1_minggu,
            'modal_1_bulan'    => $this->modal_1_bulan,
            'modal_all_in'     => $this->modal_all_in,
            'modal_all_in_1_minggu' => $this->modal_all_in_1_minggu,
            'modal_all_in_1_bulan'  => $this->modal_all_in_1_bulan,
            'status'           => $this->status,
            'catatan'          => $this->catatan,
            'photos'           => $this->whenLoaded('photos', fn() =>
                $this->photos->map(fn($p) => [
                    'id'    => $p->id,
                    'url'   => \Illuminate\Support\Facades\Storage::disk('public')->url($p->path),
                    'label' => $p->label,
                ])
            ),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
