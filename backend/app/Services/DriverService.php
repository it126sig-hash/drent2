<?php

namespace App\Services;

use App\Models\Driver;
use Illuminate\Support\Facades\Auth;

class DriverService
{
    public function getAll(array $filters = [])
    {
        $query = Driver::query()
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

        if (isset($filters['is_tetap'])) {
            $query->where('is_tetap', filter_var($filters['is_tetap'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nama', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('kontak_1', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('no_sim', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('is_tetap', 'desc') // 'desc' memastikan true/1 berada di atas
            ->orderBy('nama', 'asc')       // 'asc' agar nama urut dari A ke Z
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data)
    {
        $data['tenant_id'] = Auth::user()->tenant_id;

        // Jika branch_id tidak dikirim, gunakan branch user yang login
        if (!isset($data['branch_id'])) {
            $data['branch_id'] = Auth::user()->branch_id;
        }

        return Driver::create($data);
    }

    public function update(Driver $driver, array $data)
    {
        $driver->update($data);
        return $driver;
    }

    public function delete(Driver $driver)
    {
        return $driver->delete();
    }

    public function updateBalance(Driver $driver, int $saldo)
    {
        $driver->update(['saldo' => $saldo]);
        return $driver;
    }
}
