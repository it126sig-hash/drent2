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

        $searchTokens = $this->searchTokens($filters['search'] ?? null);
        if (! empty($searchTokens)) {
            $query->where(function ($q) use ($searchTokens) {
                foreach ($searchTokens as $token) {
                    $q->where(function ($tokenQuery) use ($token) {
                        $like = '%' . $this->escapeLike($token) . '%';

                        $tokenQuery
                            ->where('tipe', 'like', $like)
                            ->orWhere('merk', 'like', $like)
                            ->orWhere('no_polisi', 'like', $like)
                            ->orWhere('status', 'like', $like)
                            ->orWhere('catatan', 'like', $like)
                            ->orWhere('tahun', 'like', $like)
                            ->orWhereHas('rentalOwner', function ($owner) use ($like) {
                                $owner
                                    ->where('nama', 'like', $like)
                                    ->orWhere('kontak_1', 'like', $like)
                                    ->orWhere('kontak_2', 'like', $like)
                                    ->orWhere('kota', 'like', $like)
                                    ->orWhere('alamat', 'like', $like);
                            });
                    });
                }
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    private function searchTokens(?string $search): array
    {
        $search = trim(strtolower((string) $search));
        if ($search === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', $search) ?: []));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
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
