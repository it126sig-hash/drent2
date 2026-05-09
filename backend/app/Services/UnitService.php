<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitPhoto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnitService
{
    public function getAll(array $filters = [])
    {
        $query = Unit::with(['rentalOwner', 'photos'])
            ->where('tenant_id', Auth::user()->tenant_id);

        // Branch scope wajib (Global Rule #8)
        if (isset($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $query->where('branch_id', $filters['branch_id']);
        } else if (Auth::user()->role !== 'superadmin') {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('tipe', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('merk', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('no_polisi', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        // Jika branch_id tidak dikirim, gunakan branch user yang login
        if (!isset($data['branch_id'])) {
            $data['branch_id'] = Auth::user()->branch_id;
        }
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data)
    {
        $unit->update($data);
        return $unit;
    }

    public function delete(Unit $unit)
    {
        return $unit->delete();
    }

    public function uploadPhoto(Unit $unit, $file, string $label = null)
    {
        $filename = $unit->id . '_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('unit-photos', $filename, 'public');

        return $unit->photos()->create([
            'path' => $path,
            'label' => $label,
        ]);
    }

    public function deletePhoto(UnitPhoto $photo)
    {
        if (Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }
        return $photo->delete();
    }
}
