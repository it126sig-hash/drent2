<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAccountTransaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'payment_account_id',
        'related_payment_account_id',
        'finance_category_id',
        'type',
        'transfer_group_id',
        'amount',
        'signed_amount',
        'balance_before',
        'balance_after',
        'transaction_at',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'signed_amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'transaction_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function relatedPaymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'related_payment_account_id');
    }

    public function financeCategory()
    {
        return $this->belongsTo(FinanceCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
