<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantService
{
    public function update(Tenant $tenant, array $data, ?UploadedFile $logo = null): Tenant
    {
        $removeLogo = filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Bersihkan field yang bukan kolom DB
        unset($data['logo'], $data['remove_logo']);

        // Slug: immutable kalau tenant sudah punya, generate dari name kalau kosong
        if (! empty($tenant->slug)) {
            // Pertahankan slug lama, abaikan input
            unset($data['slug']);
        } elseif (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $tenant->id);
        }

        // Hapus logo lama kalau diminta atau kalau ada upload baru
        if ($removeLogo && $tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $data['logo_path'] = null;
        }

        if ($logo) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $data['logo_path'] = $logo->store('tenant-logos', 'public');
        }

        $tenant->update($data);

        return $tenant->fresh(['city']);
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
