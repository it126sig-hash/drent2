<?php

namespace App\Services;

use App\Models\CostType;
use Illuminate\Support\Facades\Auth;

class CostTypeService
{
    public function getAll(array $filters = [])
    {
        $query = CostType::query()
            ->where('tenant_id', Auth::user()->tenant_id);

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('kode', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('sort_order')->paginate($filters['per_page'] ?? 50);
    }

    public function create(array $data)
    {
        $data['tenant_id'] = Auth::user()->tenant_id;

        return CostType::create($data);
    }

    public function update(CostType $costType, array $data)
    {
        $costType->update($data);
        return $costType;
    }

    public function delete(CostType $costType)
    {
        return $costType->delete();
    }
}
