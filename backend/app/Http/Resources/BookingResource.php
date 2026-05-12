<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'kode_booking'        => $this->kode_booking,
            'status'              => $this->status,
            'lama_sewa'           => $this->lama_sewa,
            'paket_sewa'          => $this->paket_sewa,
            'harga_dealing'       => (int) $this->harga_dealing,
            'dp'                  => (int) $this->dp,
            'rekening_dp_id'      => $this->rekening_dp_id,
            'tujuan'              => $this->tujuan,
            'alamat_penjemputan'  => $this->alamat_penjemputan,
            'catatan'             => $this->catatan,
            'catatan_status'      => $this->catatan_status,
            'branch_id'           => $this->branch_id,
            'created_at'          => $this->created_at?->toISOString(),
            'created_by_user'     => $this->whenLoaded('createdBy', fn() => $this->createdBy ? [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'role' => $this->createdBy->role,
            ] : null),

            // C8: Computed financial fields
            'total_payments'      => $this->whenLoaded('payments', fn() =>
                (int) $this->payments->sum('amount')
            ),
            'total_tagihan'       => $this->whenLoaded('bookingDetails', fn() =>
                $this->computeTotalTagihan()
            ),
            'sisa_tagihan'        => $this->whenLoaded('payments', function () {
                if (!$this->relationLoaded('bookingDetails')) return null;
                $total = $this->computeTotalTagihan();
                $paid  = (int) $this->payments->sum('amount');
                return $total - $paid;
            }),
            'is_overdue'          => $this->status === 'rental_unit'
                && $this->whenLoaded('bookingDetails', fn() =>
                    $this->bookingDetails
                        ->where('status', 'aktif')
                        ->filter(fn($d) => $d->tgl_kembali && \Carbon\Carbon::parse($d->tgl_kembali)->isPast())
                        ->isNotEmpty()
                ),

            'customer'            => [
                'id'     => $this->customer?->id,
                'nama'   => $this->customer?->nama,
                'status' => $this->customer?->status,
            ],
            'booking_details'     => $this->whenLoaded('bookingDetails', function () {
                return $this->bookingDetails->map(fn($d) => [
                    'id'                 => $d->id,
                    'unit_id'            => $d->unit_id,
                    'unit_placeholder'   => $d->unit_placeholder,
                    'driver_id'          => $d->driver_id,
                    'tgl_sewa'           => $d->tgl_sewa,
                    'tgl_kembali'        => $d->tgl_kembali,
                    'harga_mobil'        => (int) $d->harga_mobil,
                    'diskon_mobil'       => (int) $d->diskon_mobil,
                    'lama_sewa'          => $d->lama_sewa,
                    'paket_sewa'         => $d->paket_sewa,
                    'pricing_mode'       => $d->pricing_mode,
                    'pricing_package_id' => $d->pricing_package_id,
                    'harga_all_in'       => $d->harga_all_in ? (int) $d->harga_all_in : null,
                    'detail_type'        => $d->detail_type,
                    'status'             => $d->status,
                    'unit'               => $d->unit ? [
                        'id'           => $d->unit->id,
                        'no_polisi'    => $d->unit->no_polisi,
                        'merk'         => $d->unit->merk,
                        'tipe'         => $d->unit->tipe,
                        'status'       => $d->unit->status,
                        'rental_owner' => $d->unit->relationLoaded('rentalOwner') && $d->unit->rentalOwner ? [
                            'id'       => $d->unit->rentalOwner->id,
                            'nama'     => $d->unit->rentalOwner->nama,
                            'is_owner' => (bool) $d->unit->rentalOwner->is_owner,
                        ] : null,
                    ] : null,
                    'driver'             => $d->driver ? [
                        'id'   => $d->driver->id,
                        'nama' => $d->driver->nama,
                    ] : null,
                    'costs'              => $d->relationLoaded('costs')
                        ? $d->costs->map(fn($c) => [
                            'id'           => $c->id,
                            'cost_type_id' => $c->cost_type_id,
                            'type'         => $c->type,
                            'label'        => $c->label,
                            'amount'       => (int) $c->amount,
                            'keterangan'   => $c->keterangan,
                            'cost_type'    => $c->relationLoaded('costType') && $c->costType ? [
                                'id'   => $c->costType->id,
                                'nama' => $c->costType->nama,
                                'kode' => $c->costType->kode,
                            ] : null,
                        ])
                        : [],
                ]);
            }),
            'payments'            => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn($p) => [
                    'id'                    => $p->id,
                    'payment_account_id'    => $p->payment_account_id,
                    'amount'                => (int) $p->amount,
                    'payment_type'          => $p->payment_type,
                    'catatan'               => $p->catatan,
                    'paid_at'               => $p->paid_at?->toISOString(),
                    'reallocated_from_id'   => $p->reallocated_from_id,
                    'created_by'            => $p->created_by,
                    'created_at'            => $p->created_at?->toISOString(),
                ]);
            }),
            'refunds'             => $this->whenLoaded('refunds', function () {
                return $this->refunds->map(fn($r) => [
                    'id'                 => $r->id,
                    'payment_account_id' => $r->payment_account_id,
                    'amount'             => (int) $r->amount,
                    'keterangan'         => $r->keterangan,
                    'refunded_at'        => $r->refunded_at?->toISOString(),
                    'created_by'         => $r->created_by,
                    'created_at'         => $r->created_at?->toISOString(),
                ]);
            }),
        ];
    }

    private function computeTotalTagihan(): int
    {
        if (!$this->relationLoaded('bookingDetails')) {
            return 0;
        }

        $activeDetail = $this->bookingDetails
            ->whereIn('status', ['aktif', 'draft'])
            ->sortByDesc('created_at')
            ->first();

        if (!$activeDetail) {
            return 0;
        }

        if ($activeDetail->pricing_mode === 'all_in') {
            return (int) (($activeDetail->harga_all_in ?? 0) * ($activeDetail->lama_sewa ?? 1));
        }

        // non_all_in: hitung dari semua details yang bukan batal
        $totalHarga = $this->bookingDetails
            ->whereNotIn('status', ['batal'])
            ->sum(fn($d) => (int) (($d->harga_mobil - $d->diskon_mobil) * ($d->lama_sewa ?? 1)));

        $totalCosts = 0;
        foreach ($this->bookingDetails->whereNotIn('status', ['batal']) as $d) {
            if ($d->relationLoaded('costs')) {
                $totalCosts += $d->costs->sum('amount');
            }
        }

        return $totalHarga + $totalCosts;
    }
}
