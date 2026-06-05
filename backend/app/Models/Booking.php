<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'customer_id',
        'created_by',
        'kode_booking',
        'status',
        'lama_sewa',
        'paket_sewa',
        'harga_dealing',
        'dp',
        'rekening_dp_id',
        'tujuan',
        'kota',
        'alamat_penjemputan',
        'catatan',
        'confirmed_at',
        'confirmed_by',
        'handled_at',
        'handled_by',
        'checked_out_at',
        'checked_out_by',
        'returned_at',
        'completed_at',
        'completed_by',
        'rental_unit_return_status',
        'rental_unit_return_reason',
        'rental_unit_return_requested_by',
        'rental_unit_return_requested_at',
        'rental_unit_return_approved_by',
        'rental_unit_return_approved_at',
        'rental_unit_return_rejected_by',
        'rental_unit_return_rejected_at',
        'rental_unit_return_rejection_note',
        'due_date',
        'cached_sisa_tagihan',
        'is_operational_completed',
        'operational_revert_status',
        'operational_revert_reason',
        'operational_revert_requested_by',
        'operational_revert_requested_at',
        'operational_revert_approved_by',
        'operational_revert_approved_at',
        'operational_revert_rejected_by',
        'operational_revert_rejected_at',
        'operational_revert_rejection_note',
    ];

    protected $casts = [
        'lama_sewa' => 'integer',
        'confirmed_at' => 'datetime',
        'handled_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'returned_at' => 'datetime',
        'completed_at' => 'datetime',
        'rental_unit_return_requested_at' => 'datetime',
        'rental_unit_return_approved_at' => 'datetime',
        'rental_unit_return_rejected_at' => 'datetime',
        'due_date' => 'datetime',
        'is_operational_completed' => 'boolean',
        'operational_revert_requested_at' => 'datetime',
        'operational_revert_approved_at' => 'datetime',
        'operational_revert_rejected_at' => 'datetime',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-10.

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function rentalUnitReturnRequester()
    {
        return $this->belongsTo(User::class, 'rental_unit_return_requested_by');
    }

    public function rentalUnitReturnApprover()
    {
        return $this->belongsTo(User::class, 'rental_unit_return_approved_by');
    }

    public function operationalRevertRequester()
    {
        return $this->belongsTo(User::class, 'operational_revert_requested_by');
    }

    public function operationalRevertApprover()
    {
        return $this->belongsTo(User::class, 'operational_revert_approved_by');
    }

    public function operationalRevertRejecter()
    {
        return $this->belongsTo(User::class, 'operational_revert_rejected_by');
    }

    public function rentalUnitReturnRejecter()
    {
        return $this->belongsTo(User::class, 'rental_unit_return_rejected_by');
    }

    public function bookingDetails()
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function physicalChecks()
    {
        return $this->hasMany(PhysicalCheck::class);
    }

    public function operationalFunds()
    {
        return $this->hasMany(DriverOperationalFund::class);
    }

    public function operationalExpenses()
    {
        return $this->hasMany(DriverOperationalExpense::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_bookings')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function rentToRentDebts()
    {
        return $this->hasMany(RentToRentDebt::class);
    }

    public function cancellation()
    {
        return $this->hasOne(BookingCancellation::class);
    }
}
