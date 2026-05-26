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
        // 1. Tambahkan join ke tabel rental_owners agar bisa melakukan pengurutan kolom relasi
        //    Gunakan select('units.*') supaya kolom yang di-return tetap milik tabel utama (Unit)
        $query = Unit::select('units.*')
            ->with(['rentalOwner', 'photos', 'city'])
            ->leftJoin('rental_owners', 'units.rental_owner_id', '=', 'rental_owners.id')
            ->where('units.tenant_id', Auth::user()->tenant_id);

        // Branch scope wajib (Global Rule #8)
        // Catatan: Tambahkan prefix 'units.' pada branch_id untuk menghindari error 'ambiguous column'
        if (isset($filters['branch_id']) && $filters['branch_id'] !== 'all') {
            $query->where('units.branch_id', $filters['branch_id']);
        } else if (Auth::user()->role !== 'superadmin') {
            $query->where('units.branch_id', Auth::user()->branch_id);
        }

        if (isset($filters['status'])) {
            $query->where('units.status', $filters['status']);
        }

        if (isset($filters['city_id']) && $filters['city_id'] !== '' && $filters['city_id'] !== null && $filters['city_id'] !== 'all') {
            $query->where('units.city_id', $filters['city_id']);
        }

        if (isset($filters['rental_owner_id']) && $filters['rental_owner_id'] !== '' && $filters['rental_owner_id'] !== null && $filters['rental_owner_id'] !== 'all') {
            $query->where('units.rental_owner_id', $filters['rental_owner_id']);
        }

        $searchTokens = $this->searchTokens($filters['search'] ?? null);
        if (! empty($searchTokens)) {
            $query->where(function ($q) use ($searchTokens) {
                foreach ($searchTokens as $token) {
                    $q->where(function ($tokenQuery) use ($token) {
                        $like = '%' . $this->escapeLike($token) . '%';

                        // Tambahkan prefix 'units.' pada kolom tabel utama agar query tidak bingung
                        $tokenQuery
                            ->where('units.tipe', 'like', $like)
                            ->orWhere('units.merk', 'like', $like)
                            ->orWhere('units.no_polisi', 'like', $like)
                            ->orWhere('units.status', 'like', $like)
                            ->orWhere('units.catatan', 'like', $like)
                            ->orWhere('units.tahun', 'like', $like)
                            ->orWhereHas('rentalOwner', function ($owner) use ($like) {
                                $owner
                                    ->where('nama', 'like', $like)
                                    ->orWhere('kontak_1', 'like', $like)
                                    ->orWhere('kontak_2', 'like', $like)
                                    ->orWhere('kota', 'like', $like)
                                    ->orWhere('alamat', 'like', $like);
                            })
                            ->orWhereHas('city', function ($city) use ($like) {
                                $city
                                    ->where('nama', 'like', $like)
                                    ->orWhere('provinsi', 'like', $like);
                            });
                    });
                }
            });
        }

        // 2. Lakukan pengurutan menggunakan kolom dari tabel rental_owners yang sudah di-join
        return $query->orderBy('rental_owners.is_owner', 'desc')
            ->orderBy('rental_owners.nama', 'asc')
            ->paginate($filters['per_page'] ?? 15);
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

    public function checkScheduleConflict(Unit $unit, string $tglSewa, string $tglKembali): array
    {
        return \App\Models\BookingDetail::query()
            ->where('unit_id', $unit->id)
            ->whereNotNull('tgl_sewa')
            ->whereNotNull('tgl_kembali')
            ->where('status', '!=', 'batal')
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['cancelled', 'draft']))
            ->where(fn($q) => $q
                ->whereBetween('tgl_sewa', [$tglSewa, $tglKembali])
                ->orWhereBetween('tgl_kembali', [$tglSewa, $tglKembali])
                ->orWhere(fn($q2) => $q2
                    ->where('tgl_sewa', '<=', $tglSewa)
                    ->where('tgl_kembali', '>=', $tglKembali)
                )
            )
            ->with('booking:id,kode_booking,status')
            ->get(['id', 'booking_id', 'tgl_sewa', 'tgl_kembali', 'status'])
            ->map(fn($d) => [
                'kode_booking' => $d->booking?->kode_booking,
                'status'       => $d->booking?->status,
                'tgl_sewa'     => $d->tgl_sewa,
                'tgl_kembali'  => $d->tgl_kembali,
            ])
            ->values()
            ->all();
    }

    public function batchUpdateCity(array $data)
    {
        $query = Unit::where('tenant_id', Auth::user()->tenant_id);

        if ($data['type'] === 'by_ids') {
            $query->whereIn('id', $data['ids']);
        } elseif ($data['type'] === 'by_owner') {
            $query->where('rental_owner_id', $data['rental_owner_id']);
        }

        // Branch scope
        if (Auth::user()->role !== 'superadmin') {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        $query->update([
            'city_id' => $data['city_id']
        ]);
    }
}
