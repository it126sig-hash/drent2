<?php

namespace App\Http\Resources;

use App\Services\BookingBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'nama'             => $this->nama,
            'kontak_1'         => $this->kontak_1,
            'kontak_2'         => $this->kontak_2,
            'email'            => $this->email,
            'alamat'           => $this->alamat,
            'kota'             => $this->kota,
            'status'           => $this->status,
            'has_apply_member' => $this->has_apply_member,
            'member_status'    => $this->whenLoaded('member', fn() => $this->member?->status_member),
            'member_expired_at' => $this->whenLoaded('member', fn() => $this->member?->tanggal_exp?->format('Y-m-d')),
            'member'           => $this->when($this->relationLoaded('bookings') && $this->relationLoaded('member'), fn() => $this->member ? [
                'id' => $this->member->id,
                'id_member' => $this->member->id_member,
                'status_member' => $this->member->status_member,
                'tanggal_survey' => $this->member->tanggal_survey?->format('Y-m-d'),
                'tanggal_aktif' => $this->member->tanggal_aktif?->format('Y-m-d'),
                'tanggal_exp' => $this->member->tanggal_exp?->format('Y-m-d'),
                'surveyor' => $this->member->relationLoaded('surveyor') && $this->member->surveyor ? [
                    'id' => $this->member->surveyor->id,
                    'name' => $this->member->surveyor->name,
                ] : null,
                'catatan' => $this->member->catatan,
                'pekerjaan_status' => $this->member->pekerjaan_status,
                'nama_kantor' => $this->member->nama_kantor,
                'alamat_kantor' => $this->member->alamat_kantor,
                'kontak_kantor' => $this->member->kontak_kantor,
                'jabatan' => $this->member->jabatan,
                'pj_nama' => $this->member->pj_nama,
                'pj_kontak' => $this->member->pj_kontak,
                'pj_hubungan' => $this->member->pj_hubungan,
                'status_pernikahan' => $this->member->status_pernikahan,
                'rumah_status' => $this->member->rumah_status,
                'rumah_lokasi' => $this->member->rumah_lokasi,
            ] : null),
            'rental_history'   => $this->whenLoaded('bookings', fn() => $this->bookings->map(function ($booking) {
                $detail = $booking->relationLoaded('bookingDetails')
                    ? ($booking->bookingDetails->firstWhere('status', 'aktif')
                        ?? $booking->bookingDetails->firstWhere('detail_type', 'initial')
                        ?? $booking->bookingDetails->sortByDesc('tgl_sewa')->first())
                    : null;

                $billing = app(BookingBillingService::class);

                return [
                    'id' => $booking->id,
                    'kode_booking' => $booking->kode_booking,
                    'status' => $booking->status,
                    'tgl_sewa' => $detail?->tgl_sewa,
                    'tgl_kembali' => $detail?->tgl_kembali,
                    'completed_at' => $booking->completed_at?->toISOString(),
                    'returned_at' => $booking->returned_at?->toISOString(),
                    'kota' => $booking->kota,
                    'tujuan' => $booking->tujuan,
                    'catatan' => $booking->catatan,
                    'catatan_status' => $booking->catatan_status,
                    'total_tagihan' => $billing->totalTagihan($booking),
                    'sisa_tagihan' => $billing->sisaTagihan($booking),
                    'unit' => $detail?->unit ? [
                        'id' => $detail->unit->id,
                        'no_polisi' => $detail->unit->no_polisi,
                        'merk' => $detail->unit->merk,
                        'tipe' => $detail->unit->tipe,
                    ] : null,
                    'details' => $booking->relationLoaded('bookingDetails')
                        ? $booking->bookingDetails->map(fn($item) => [
                            'id' => $item->id,
                            'detail_type' => $item->detail_type,
                            'status' => $item->status,
                            'tgl_sewa' => $item->tgl_sewa,
                            'tgl_kembali' => $item->tgl_kembali,
                            'lama_sewa' => $item->lama_sewa,
                            'paket_sewa' => $item->paket_sewa,
                            'unit' => $item->unit ? [
                                'no_polisi' => $item->unit->no_polisi,
                                'merk' => $item->unit->merk,
                                'tipe' => $item->unit->tipe,
                            ] : null,
                        ])
                        : [],
                ];
            })),
            'catatan'          => $this->catatan,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
