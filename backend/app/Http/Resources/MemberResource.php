<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'id_member' => $this->id_member,
            'status_member' => $this->status_member,
            'tanggal_survey' => $this->tanggal_survey?->format('Y-m-d'),
            'tanggal_aktif' => $this->tanggal_aktif?->format('Y-m-d'),
            'tanggal_exp' => $this->tanggal_exp?->format('Y-m-d'),
            'surveyor_id' => $this->surveyor_id,
            'surveyor' => new V1\UserResource($this->whenLoaded('surveyor')),
            'catatan' => $this->catatan,

            // Identity (Flags or URLs via protected endpoint)
            'has_foto_wajah' => (bool) $this->foto_wajah,
            'has_dokumen_identitas' => (bool) $this->dokumen_identitas,
            'identitas_type' => $this->identitas_type,
            'dokumen_pendukung_count' => count($this->dokumen_pendukung ?? []),

            // Job
            'nama_kantor' => $this->nama_kantor,
            'alamat_kantor' => $this->alamat_kantor,
            'kontak_kantor' => $this->kontak_kantor,
            'jabatan' => $this->jabatan,
            'nama_atasan' => $this->nama_atasan,
            'pekerjaan_status' => $this->pekerjaan_status,

            // Social
            'pj_nama' => $this->pj_nama,
            'pj_kontak' => $this->pj_kontak,
            'pj_hubungan' => $this->pj_hubungan,
            'ortu_nama' => $this->ortu_nama,
            'ortu_alamat' => $this->ortu_alamat,
            'ortu_kontak' => $this->ortu_kontak,
            'status_pernikahan' => $this->status_pernikahan,
            'rumah_status' => $this->rumah_status,
            'rumah_lokasi' => $this->rumah_lokasi,

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
