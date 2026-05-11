<?php

namespace App\Services;

use App\Models\PricingPackage;
use Illuminate\Support\Facades\Auth;

class PricingPackageService
{
    public function getAll(array $filters = [])
    {
        $query = PricingPackage::query()
            ->where('tenant_id', Auth::user()->tenant_id);

        // Branch scope
        if (isset($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $query->where('branch_id', $filters['branch_id']);
        } else if (Auth::user()->role !== 'superadmin') {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama_paket', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('keterangan', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        $data['tenant_id'] = Auth::user()->tenant_id;

        if (!isset($data['branch_id'])) {
            $data['branch_id'] = Auth::user()->branch_id;
        }

        return PricingPackage::create($data);
    }

    public function update(PricingPackage $pricingPackage, array $data)
    {
        $pricingPackage->update($data);
        return $pricingPackage;
    }

    public function delete(PricingPackage $pricingPackage)
    {
        return $pricingPackage->delete();
    }
}
