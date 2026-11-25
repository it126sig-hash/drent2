<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetCardBookingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'driver_id' => $this->driver_id,
            'tgl_sewa' => $this->tgl_sewa,
            'tgl_kembali' => $this->tgl_kembali,
            'pricing_mode' => $this->pricing_mode,
            'lama_sewa' => $this->lama_sewa,
            'detail_type' => $this->detail_type,
            'status' => $this->status,
            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id' => $this->booking->id,
                'kode_booking' => $this->booking->kode_booking,
                'tujuan' => $this->booking->tujuan,
                'kota' => $this->booking->kota,
                'customer' => $this->booking->relationLoaded('customer') && $this->booking->customer ? [
                    'id' => $this->booking->customer->id,
                    'nama' => $this->booking->customer->nama,
                ] : null,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'nama' => $this->driver->nama,
                'is_tetap' => (bool) $this->driver->is_tetap,
                'user_id' => $this->driver->user_id,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'no_polisi' => $this->unit->no_polisi,
                'merk' => $this->unit->merk,
                'tipe' => $this->unit->tipe,
            ] : null),
            'fleet_card_funds' => $this->whenLoaded('fleetCardFunds', fn () =>
                FleetCardFundResource::collection($this->fleetCardFunds)
            ),
            'summary' => [
                'topup_total' => (int) ($this->fleet_card_topup_total ?? 0),
                'approved_expense_total' => (int) ($this->fleet_card_approved_expense_total ?? 0),
                'approved_return_total' => (int) ($this->fleet_card_approved_return_total ?? 0),
                'active_fund_count' => (int) ($this->fleet_card_active_fund_count ?? 0),
                'pending_review_count' => (int) ($this->fleet_card_pending_review_count ?? 0),
            ],
        ];
    }
}
