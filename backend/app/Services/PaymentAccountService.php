<?php

namespace App\Services;

use App\Models\PaymentAccount;
use Illuminate\Support\Facades\Auth;

class PaymentAccountService
{
    public function getAll(array $filters = [])
    {
        $query = PaymentAccount::query()
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
                $q->where('nama_bank', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('nomor_rekening', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('atas_nama', 'like', '%' . $filters['search'] . '%');
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

        return PaymentAccount::create($data);
    }

    public function update(PaymentAccount $paymentAccount, array $data)
    {
        $paymentAccount->update($data);
        return $paymentAccount;
    }

    public function delete(PaymentAccount $paymentAccount)
    {
        return $paymentAccount->delete();
    }
}
