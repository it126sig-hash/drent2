<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
    // Keputusan ini belum final per 2026-05-09.

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'id_member',
        'status_member',
        'tanggal_survey',
        'tanggal_aktif',
        'tanggal_exp',
        'surveyor_id',
        'catatan',
        'foto_wajah',
        'dokumen_identitas',
        'identitas_type',
        'dokumen_pendukung',
        'nama_kantor',
        'alamat_kantor',
        'kontak_kantor',
        'jabatan',
        'nama_atasan',
        'pekerjaan_status',
        'pj_nama',
        'pj_kontak',
        'pj_hubungan',
        'ortu_nama',
        'ortu_alamat',
        'ortu_kontak',
        'status_pernikahan',
        'rumah_status',
        'rumah_lokasi',
    ];

    protected $casts = [
        'tanggal_survey' => 'date',
        'tanggal_aktif' => 'date',
        'tanggal_exp' => 'date',
        'dokumen_pendukung' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function surveyor()
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function extensions()
    {
        return $this->hasMany(MemberExtension::class)->latest();
    }
}
