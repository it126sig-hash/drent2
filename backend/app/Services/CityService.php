<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Facades\Auth;

class CityService
{
    public function getAll(array $filters = [])
    {
        $query = City::query()
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

        return City::create($data);
    }

    public function update(City $city, array $data): City
    {
        $city->update($data);

        return $city;
    }

    public function delete(City $city): bool
    {
        return (bool) $city->delete();
    }
}
