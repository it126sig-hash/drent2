<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
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
            'tenant_id'   => $this->tenant_id,
            'branch_id'   => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn() => $this->branch->name),
            // Always include permissions so the frontend always receives a valid array.
            // This resource is used exclusively by AuthController (login, me) and
            // user detail endpoints — it is safe and necessary to always compute this.
            'permissions' => (new \App\Services\PermissionService())->getEffectivePermissions($this->resource),
            'created_at'  => $this->created_at,
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
