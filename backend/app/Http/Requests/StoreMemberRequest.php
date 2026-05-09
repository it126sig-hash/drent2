<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id|unique:members,customer_id',
            'status_member' => 'nullable|in:Pending,Aktif,Tidak Aktif,Ditolak',
            'tanggal_survey' => 'nullable|date',
            'surveyor_id' => 'nullable|exists:users,id',
            'catatan' => 'nullable|string',
            
            // Identity
            'foto_wajah' => 'nullable|image|max:2048',
            'dokumen_identitas' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'identitas_type' => 'nullable|string',
            'dokumen_pendukung_files.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',

            // Job
            'nama_kantor' => 'nullable|string',
            'alamat_kantor' => 'nullable|string',
            'kontak_kantor' => 'nullable|string',
            'jabatan' => 'nullable|string',
            'nama_atasan' => 'nullable|string',
            'pekerjaan_status' => 'nullable|string',

            // Social
            'pj_nama' => 'nullable|string',
            'pj_kontak' => 'nullable|string',
            'pj_hubungan' => 'nullable|string',
            'ortu_nama' => 'nullable|string',
            'ortu_alamat' => 'nullable|string',
            'ortu_kontak' => 'nullable|string',
            'status_pernikahan' => 'nullable|string',
            'rumah_status' => 'nullable|string',
            'rumah_lokasi' => 'nullable|string',
        ];
    }
}
