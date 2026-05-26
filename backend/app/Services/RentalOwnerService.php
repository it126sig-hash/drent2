<?php

namespace App\Services;

use App\Models\RentalOwner;
use Illuminate\Support\Facades\Auth;

class RentalOwnerService
{
    public function getAll(array $filters = [])
    {
        $query = RentalOwner::where('tenant_id', Auth::user()->tenant_id);

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('kontak_1', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('kota', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('alamat', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Menambahkan pengurutan berdasarkan is_owner dan nama
        return $query->orderBy('is_owner', 'desc') // 'desc' memastikan true/1 berada di atas
            ->orderBy('nama', 'asc')       // 'asc' agar nama urut dari A ke Z
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getById(int $id)
    {
        return RentalOwner::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
    }

    public function create(array $data)
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        return RentalOwner::create($data);
    }

    public function update(RentalOwner $rentalOwner, array $data)
    {
        $rentalOwner->update($data);
        return $rentalOwner;
    }

    public function delete(RentalOwner $rentalOwner)
    {
        return $rentalOwner->delete();
    }
}
