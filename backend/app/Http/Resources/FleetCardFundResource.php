<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetCardFundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'fleet_card_id' => $this->fleet_card_id,
            'booking_id' => $this->booking_id,
            'booking_detail_id' => $this->booking_detail_id,
            'driver_id' => $this->driver_id,
            'payment_account_id' => $this->payment_account_id,
            'amount' => (int) $this->amount,
            'paid_at' => $this->paid_at?->toISOString(),
            'recipient_destination' => $this->recipient_destination,
            'notes' => $this->notes,
            'status' => $this->status,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'close_note' => $this->close_note,
            'created_at' => $this->created_at?->toISOString(),
            'created_by' => $this->created_by,
            'fleet_card' => $this->whenLoaded('fleetCard', fn () => $this->fleetCard ? [
                'id' => $this->fleetCard->id,
                'code' => $this->fleetCard->code,
                'label' => $this->fleetCard->label,
                'saldo' => (int) $this->fleetCard->saldo,
                'status' => $this->fleetCard->status,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'role' => $this->creator->role,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'nama' => $this->driver->nama,
                'is_tetap' => (bool) $this->driver->is_tetap,
                'user_id' => $this->driver->user_id,
            ] : null),
            'payment_account' => $this->whenLoaded('paymentAccount', fn () => $this->paymentAccount ? [
                'id' => $this->paymentAccount->id,
                'nama_bank' => $this->paymentAccount->nama_bank,
                'nomor_rekening' => $this->paymentAccount->nomor_rekening,
                'atas_nama' => $this->paymentAccount->atas_nama,
            ] : null),
            'booking' => $this->whenLoaded('booking', fn () => $this->booking ? [
                'id' => $this->booking->id,
                'kode_booking' => $this->booking->kode_booking,
                'status' => $this->booking->status,
                'tujuan' => $this->booking->tujuan,
                'kota' => $this->booking->kota,
                'customer' => $this->booking->relationLoaded('customer') && $this->booking->customer ? [
                    'id' => $this->booking->customer->id,
                    'nama' => $this->booking->customer->nama,
                ] : null,
            ] : null),
            'booking_detail' => $this->whenLoaded('bookingDetail', fn () => $this->bookingDetail ? [
                'id' => $this->bookingDetail->id,
                'tgl_sewa' => $this->bookingDetail->tgl_sewa,
                'tgl_kembali' => $this->bookingDetail->tgl_kembali,
                'detail_type' => $this->bookingDetail->detail_type,
                'unit' => $this->bookingDetail->relationLoaded('unit') && $this->bookingDetail->unit ? [
                    'id' => $this->bookingDetail->unit->id,
                    'no_polisi' => $this->bookingDetail->unit->no_polisi,
                    'merk' => $this->bookingDetail->unit->merk,
                    'tipe' => $this->bookingDetail->unit->tipe,
                ] : null,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'cost_type_id' => $item->cost_type_id,
                'label' => $item->label,
                'planned_amount' => (int) $item->planned_amount,
                'notes' => $item->notes,
                'cost_type' => $item->relationLoaded('costType') && $item->costType ? [
                    'id' => $item->costType->id,
                    'nama' => $item->costType->nama,
                    'kode' => $item->costType->kode,
                ] : null,
            ])),
            'expenses' => $this->whenLoaded('expenses', fn () =>
                FleetCardExpenseResource::collection($this->expenses)
            ),
            'summary' => [
                'approved_expense_total' => (int) $this->approvedExpenseTotal(),
                'approved_return_total' => (int) $this->approvedReturnTotal(),
                'pending_review_count' => (int) $this->pendingReviewCount(),
                'pending_driver_review_count' => (int) $this->pendingDriverReviewCount(),
                'card_remaining_balance' => (int) $this->remainingAmount(),
            ],
        ];
    }
}
