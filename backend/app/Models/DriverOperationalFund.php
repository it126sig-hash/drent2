<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverOperationalFund extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'booking_id',
        'booking_detail_id',
        'driver_id',
        'payment_account_id',
        'fund_type',
        'amount',
        'paid_at',
        'recipient_destination',
        'notes',
        'status',
        'accepted_at',
        'accepted_by',
        'created_by',
        'cancelled_by',
        'closed_at',
        'closed_by',
        'close_note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'date',
        'accepted_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function items()
    {
        return $this->hasMany(DriverOperationalFundItem::class);
    }

    public function expenses()
    {
        return $this->hasMany(DriverOperationalExpense::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approvedExpenseTotal(): int
    {
        $expenses = $this->relationLoaded('expenses')
            ? $this->expenses
            : $this->expenses()->get();

        return (int) $expenses
            ->whereIn('status', ['approved', 'void_requested'])
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function approvedReturnTotal(): int
    {
        $expenses = $this->relationLoaded('expenses')
            ? $this->expenses
            : $this->expenses()->get();

        return (int) $expenses
            ->whereIn('status', ['approved', 'void_requested'])
            ->where('type', 'return')
            ->sum('amount');
    }

    public function pendingReviewCount(): int
    {
        $expenses = $this->relationLoaded('expenses')
            ? $this->expenses
            : $this->expenses()->get();

        return $expenses->where('status', 'submitted')->count();
    }

    public function pendingDriverReviewCount(): int
    {
        $expenses = $this->relationLoaded('expenses')
            ? $this->expenses
            : $this->expenses()->get();

        return $expenses
            ->where('status', 'submitted')
            ->where('source', 'driver')
            ->count();
    }

    public function remainingAmount(): int
    {
        if (($this->fund_type ?? 'operational') === 'salary') {
            return 0;
        }

        return max(0, (int) $this->amount - $this->approvedExpenseTotal() - $this->approvedReturnTotal());
    }
}
