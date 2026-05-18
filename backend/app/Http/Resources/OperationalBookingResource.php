<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'tujuan' => $this->tujuan,
            'kota' => $this->kota,
            'created_at' => $this->created_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'nama' => $this->customer->nama,
                'kontak_1' => $this->customer->kontak_1,
            ] : null),
            'booking_details' => $this->whenLoaded('bookingDetails', fn () =>
                $this->bookingDetails->map(fn ($detail) => [
                    'id' => $detail->id,
                    'driver_id' => $detail->driver_id,
                    'tgl_sewa' => $detail->tgl_sewa,
                    'tgl_kembali' => $detail->tgl_kembali,
                    'pricing_mode' => $detail->pricing_mode,
                    'harga_all_in' => $detail->harga_all_in ? (int) $detail->harga_all_in : null,
                    'lama_sewa' => $detail->lama_sewa,
                    'detail_type' => $detail->detail_type,
                    'status' => $detail->status,
                    'driver' => $detail->relationLoaded('driver') && $detail->driver ? [
                        'id' => $detail->driver->id,
                        'nama' => $detail->driver->nama,
                        'saldo' => (int) $detail->driver->saldo,
                        'is_tetap' => (bool) $detail->driver->is_tetap,
                        'user_id' => $detail->driver->user_id,
                    ] : null,
                    'unit' => $detail->relationLoaded('unit') && $detail->unit ? [
                        'id' => $detail->unit->id,
                        'no_polisi' => $detail->unit->no_polisi,
                        'merk' => $detail->unit->merk,
                        'tipe' => $detail->unit->tipe,
                    ] : null,
                    'costs' => $detail->relationLoaded('costs')
                        ? $detail->costs->map(fn ($cost) => [
                            'id' => $cost->id,
                            'cost_type_id' => $cost->cost_type_id,
                            'label' => $cost->label,
                            'type' => $cost->type,
                            'amount' => (int) $cost->amount,
                            'keterangan' => $cost->keterangan,
                            'cost_type' => $cost->relationLoaded('costType') && $cost->costType ? [
                                'id' => $cost->costType->id,
                                'nama' => $cost->costType->nama,
                                'kode' => $cost->costType->kode,
                            ] : null,
                        ])
                        : [],
                ])
            ),
            'operational_funds' => $this->whenLoaded('operationalFunds', fn () =>
                DriverOperationalFundResource::collection($this->operationalFunds)
            ),
            'summary' => [
                'booking_operational_total' => (int) $this->booking_operational_total,
                'all_in_total' => (int) $this->all_in_total,
                'finance_disbursed_total' => (int) $this->finance_disbursed_total,
                'driver_salary_total' => (int) $this->driver_salary_total,
                'approved_expense_total' => (int) $this->approved_expense_total,
                'approved_reimbursement_total' => (int) $this->approved_expense_total,
                'approved_return_total' => (int) $this->approved_return_total,
                'pending_review_count' => (int) $this->pending_review_count,
                'pending_driver_review_count' => (int) $this->pending_driver_review_count,
                'pending_driver_acceptance_count' => (int) $this->pending_driver_acceptance_count,
                'remaining_amount' => (int) $this->remaining_amount,
                'active_fund_count' => (int) $this->active_fund_count,
                'closed_fund_count' => (int) $this->closed_fund_count,
            ],
        ];
    }
}
