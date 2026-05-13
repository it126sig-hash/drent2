<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalCheck extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'booking_id',
        'booking_detail_id',
        'type',
        'status',
        'km_odometer',
        'fuel_level',
        'fuel_marker_x',
        'fuel_marker_y',
        'notes',
        'requested_at',
        'requested_by',
        'inspected_at',
        'inspected_by',
        'skipped_at',
        'skipped_by',
    ];

    protected $casts = [
        'km_odometer' => 'integer',
        'fuel_marker_x' => 'float',
        'fuel_marker_y' => 'float',
        'requested_at' => 'datetime',
        'inspected_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per PRD retensi data.

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingDetail()
    {
        return $this->belongsTo(BookingDetail::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function skippedBy()
    {
        return $this->belongsTo(User::class, 'skipped_by');
    }

    public function sections()
    {
        return $this->hasMany(PhysicalCheckSection::class);
    }

    public function photos()
    {
        return $this->hasMany(PhysicalCheckPhoto::class);
    }

    public function checklists()
    {
        return $this->hasMany(PhysicalCheckChecklist::class);
    }

    public function signatures()
    {
        return $this->hasMany(PhysicalCheckSignature::class);
    }
}
