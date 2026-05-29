<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'branch_name' => $this->branch?->name,
            'name'        => $this->name,
            'email'       => $this->email,
            'nik'         => $this->nik,
            'alamat'      => $this->alamat,
            'no_rekening' => $this->no_rekening,
            'bank'        => $this->bank,
            'atas_nama'   => $this->atas_nama,
            'kontak'      => $this->kontak,
            'foto_profile_path' => $this->foto_profile_path,
            'foto_profile_url' => $this->publicStorageUrl($this->foto_profile_path),
            'role'        => $this->role,
            'role_label'  => $this->role_label,
            'driver_id'   => $this->driver?->id,
            'driver_name' => $this->driver?->nama,
            'is_active'   => (bool) $this->is_active,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }

    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $baseUrl = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');

        return $baseUrl . '/storage/' . ltrim($path, '/');
    }
}
