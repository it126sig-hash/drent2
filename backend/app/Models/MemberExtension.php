<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'old_exp_date',
        'new_exp_date',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'old_exp_date' => 'date',
        'new_exp_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
