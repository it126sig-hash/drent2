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
        'status',
        'void_reason',
        'void_requested_by',
        'void_requested_at',
        'void_approved_by',
        'void_approved_at',
        'void_rejected_by',
        'void_rejected_at',
        'void_rejection_note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'signed_amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'transaction_at' => 'datetime',
        'void_requested_at' => 'datetime',
        'void_approved_at' => 'datetime',
        'void_rejected_at' => 'datetime',
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

    public function voidRequester()
    {
        return $this->belongsTo(User::class, 'void_requested_by');
    }

    public function voidApprover()
    {
        return $this->belongsTo(User::class, 'void_approved_by');
    }

    public function voidRejecter()
    {
        return $this->belongsTo(User::class, 'void_rejected_by');
    }
}
