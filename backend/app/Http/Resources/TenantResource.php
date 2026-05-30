<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'is_active'  => (bool) $this->is_active,
            'phone'      => $this->phone,
            'phone_alt'  => $this->phone_alt,
            'email'      => $this->email,
            'website'    => $this->website,
            'instagram'  => $this->instagram,
            'tiktok'     => $this->tiktok,
            'facebook'   => $this->facebook,
            'logo_path'  => $this->logo_path,
            'logo_url'   => $this->publicStorageUrl($this->logo_path),
            'city_id'    => $this->city_id,
            'city'       => $this->city
                ? [
                    'id'       => $this->city->id,
                    'nama'     => $this->city->nama,
                    'provinsi' => $this->city->provinsi,
                ]
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
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
