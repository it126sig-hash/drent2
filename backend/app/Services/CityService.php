<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Facades\Auth;

class CityService
{
    public function getAll(array $filters = [])
    {
        $query = City::query()
            ->with('province')
            ->where('tenant_id', Auth::user()->tenant_id);

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('provinsi', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('nama')->paginate($filters['per_page'] ?? 50);
    }

    public function create(array $data): City
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        $data = $this->syncProvinceName($data);

        return City::create($data)->load('province');
    }

    public function update(City $city, array $data): City
    {
        $data = $this->syncProvinceName($data);
        $city->update($data);

        return $city->load('province');
    }

    private function syncProvinceName(array $data): array
    {
        if (array_key_exists('province_id', $data)) {
            $data['provinsi'] = $data['province_id']
                ? \App\Models\Province::find($data['province_id'])?->nama
                : null;
        }

        return $data;
    }

    public function delete(City $city): bool
    {
        return (bool) $city->delete();
    }
}
