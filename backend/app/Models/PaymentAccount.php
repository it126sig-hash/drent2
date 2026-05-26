<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'current_balance' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function rentToRentPayments()
    {
        return $this->hasMany(RentToRentPayment::class);
    }

    public function transactions()
    {
        return $this->hasMany(PaymentAccountTransaction::class);
    }
}
