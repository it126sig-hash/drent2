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
            'kota'                => $this->kota,
            'alamat_penjemputan'  => $this->alamat_penjemputan,
            'catatan'             => $this->catatan,
            'catatan_status'      => $this->catatan_status,
            'branch_id'           => $this->branch_id,
            'confirmed_at'        => $this->confirmed_at?->toISOString(),
            'handled_at'          => $this->handled_at?->toISOString(),
            'checked_out_at'      => $this->checked_out_at?->toISOString(),
            'returned_at'         => $this->returned_at?->toISOString(),
            'completed_at'        => $this->completed_at?->toISOString(),
            'due_date'            => $this->due_date?->toISOString(),
            'created_at'          => $this->created_at?->toISOString(),
            'rental_unit_return_request' => [
                'status' => $this->rental_unit_return_status,
                'reason' => $this->rental_unit_return_reason,
                'requested_at' => $this->rental_unit_return_requested_at?->toISOString(),
                'approved_at' => $this->rental_unit_return_approved_at?->toISOString(),
                'rejected_at' => $this->rental_unit_return_rejected_at?->toISOString(),
                'rejection_note' => $this->rental_unit_return_rejection_note,
                'requester' => $this->whenLoaded('rentalUnitReturnRequester', fn() => $this->rentalUnitReturnRequester ? [
                    'id' => $this->rentalUnitReturnRequester->id,
                    'name' => $this->rentalUnitReturnRequester->name,
                ] : null),
                'approver' => $this->whenLoaded('rentalUnitReturnApprover', fn() => $this->rentalUnitReturnApprover ? [
                    'id' => $this->rentalUnitReturnApprover->id,
                    'name' => $this->rentalUnitReturnApprover->name,
                ] : null),
                'rejecter' => $this->whenLoaded('rentalUnitReturnRejecter', fn() => $this->rentalUnitReturnRejecter ? [
                    'id' => $this->rentalUnitReturnRejecter->id,
                    'name' => $this->rentalUnitReturnRejecter->name,
                ] : null),
            ],
            'created_by_user'     => $this->whenLoaded('createdBy', fn() => $this->createdBy ? [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'role' => $this->createdBy->role,
            ] : null),
            'confirmed_by_user'   => $this->stageUser('confirmedBy'),
            'handled_by_user'     => $this->stageUser('handledBy'),
            'checked_out_by_user' => $this->stageUser('checkedOutBy'),
            'completed_by_user'   => $this->stageUser('completedBy'),
            'physical_check_summary' => $this->whenLoaded('physicalChecks', fn() => [
                'departure' => $this->physicalCheckSummary('departure'),
                'return' => $this->physicalCheckSummary('return'),
            ]),

            // C8: Computed financial fields
            'total_payments'      => $this->whenLoaded('payments', fn() =>
                (int) $this->activePayments()->sum('amount')
            ),
            'total_tagihan'       => $this->whenLoaded('bookingDetails', fn() =>
                $this->computeTotalTagihan()
            ),
            'sisa_tagihan'        => $this->whenLoaded('payments', function () {
                if (!$this->relationLoaded('bookingDetails')) return null;
                $total = $this->computeTotalTagihan();
                $paid  = (int) $this->activePayments()->sum('amount');
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
                'email'  => $this->customer?->email,
                'kota'   => $this->customer?->kota,
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
                            'is_additional' => (bool) $c->is_additional,
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
                    'status'                => $p->status ?? 'active',
                    'catatan'               => $p->catatan,
                    'void_reason'           => $p->void_reason,
                    'void_requested_at'     => $p->void_requested_at?->toISOString(),
                    'void_approved_at'      => $p->void_approved_at?->toISOString(),
                    'void_rejected_at'      => $p->void_rejected_at?->toISOString(),
                    'void_rejection_note'   => $p->void_rejection_note,
                    'paid_at'               => $p->paid_at?->toISOString(),
                    'reallocated_from_id'   => $p->reallocated_from_id,
                    'created_by'            => $p->created_by,
                    'created_at'            => $p->created_at?->toISOString(),
                    'creator'               => $p->relationLoaded('creator') && $p->creator ? [
                        'id'   => $p->creator->id,
                        'name' => $p->creator->name,
                    ] : null,
                    'void_requester'        => $p->relationLoaded('voidRequester') && $p->voidRequester ? [
                        'id'   => $p->voidRequester->id,
                        'name' => $p->voidRequester->name,
                    ] : null,
                    'void_approver'         => $p->relationLoaded('voidApprover') && $p->voidApprover ? [
                        'id'   => $p->voidApprover->id,
                        'name' => $p->voidApprover->name,
                    ] : null,
                    'void_rejecter'         => $p->relationLoaded('voidRejecter') && $p->voidRejecter ? [
                        'id'   => $p->voidRejecter->id,
                        'name' => $p->voidRejecter->name,
                    ] : null,
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

    private function stageUser(string $relation)
    {
        return $this->whenLoaded($relation, function () use ($relation) {
            $user = $this->{$relation};

            return $user ? [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ] : null;
        });
    }

    private function physicalCheckSummary(string $type): array
    {
        $check = $this->physicalChecks
            ->where('type', $type)
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $check?->id,
            'status' => $check?->status ?? 'not_requested',
            'requested_at' => $check?->requested_at?->toISOString(),
            'inspected_at' => $check?->inspected_at?->toISOString(),
            'skipped_at' => $check?->skipped_at?->toISOString(),
        ];
    }

    private function computeTotalTagihan(): int
    {
        if (!$this->relationLoaded('bookingDetails')) {
            return 0;
        }

        $billableDetails = $this->bookingDetails->whereNotIn('status', ['batal']);

        if ($billableDetails->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($billableDetails as $d) {
            $duration = $d->lama_sewa ?? 1;
            if ($d->pricing_mode === 'all_in') {
                $total += (int) (($d->harga_all_in ?? 0) * $duration);

                if ($d->relationLoaded('costs')) {
                    $total += $d->costs
                        ->where('type', 'diskon')
                        ->sum(fn($cost) =>
                            -((int) $cost->amount)
                        );
                }
            } else {
                $total += (int) (($d->harga_mobil - $d->diskon_mobil) * $duration);

                if ($d->relationLoaded('costs')) {
                    $total += $d->costs->sum(fn($cost) =>
                        $cost->type === 'diskon' ? -((int) $cost->amount) : (int) $cost->amount
                    );
                }
            }
        }

        return $total;
    }

    private function activePayments()
    {
        return $this->payments->filter(fn($payment) => ($payment->status ?? 'active') !== 'voided');
    }
}
