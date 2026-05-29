<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverOperationalFundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'booking_id' => $this->booking_id,
            'booking_detail_id' => $this->booking_detail_id,
            'driver_id' => $this->driver_id,
            'payment_account_id' => $this->payment_account_id,
            'fund_type' => $this->fund_type ?? 'operational',
            'is_salary' => ($this->fund_type ?? 'operational') === 'salary',
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
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'role' => $this->creator->role,
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'nama' => $this->driver->nama,
                'saldo' => (int) $this->driver->saldo,
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
                'pricing_mode' => $this->bookingDetail->pricing_mode,
                'harga_all_in' => $this->bookingDetail->harga_all_in ? (int) $this->bookingDetail->harga_all_in : null,
                'lama_sewa' => $this->bookingDetail->lama_sewa,
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
                DriverOperationalExpenseResource::collection($this->expenses)
            ),
            'booking_funds' => $this->when(
                $this->relationLoaded('booking')
                    && $this->booking
                    && $this->booking->relationLoaded('operationalFunds'),
                fn () => $this->booking->operationalFunds->map(fn ($fund) => $this->fundSnapshot($fund))->values()
            ),
            'summary' => [
                'approved_expense_total' => (int) $this->approvedExpenseTotal(),
                'approved_reimbursement_total' => (int) $this->approvedExpenseTotal(),
                'approved_return_total' => (int) $this->approvedReturnTotal(),
                'pending_review_count' => (int) $this->pendingReviewCount(),
                'pending_driver_review_count' => (int) $this->pendingDriverReviewCount(),
                'remaining_amount' => (int) $this->remainingAmount(),
            ],
        ];
    }

    private function fundSnapshot($fund): array
    {
        return [
            'id' => $fund->id,
            'booking_id' => $fund->booking_id,
            'booking_detail_id' => $fund->booking_detail_id,
            'driver_id' => $fund->driver_id,
            'fund_type' => $fund->fund_type ?? 'operational',
            'is_salary' => ($fund->fund_type ?? 'operational') === 'salary',
            'amount' => (int) $fund->amount,
            'paid_at' => $fund->paid_at?->toISOString(),
            'recipient_destination' => $fund->recipient_destination,
            'notes' => $fund->notes,
            'status' => $fund->status,
            'accepted_at' => $fund->accepted_at?->toISOString(),
            'closed_at' => $fund->closed_at?->toISOString(),
            'close_note' => $fund->close_note,
            'created_at' => $fund->created_at?->toISOString(),
            'driver' => $fund->relationLoaded('driver') && $fund->driver ? [
                'id' => $fund->driver->id,
                'nama' => $fund->driver->nama,
                'saldo' => (int) $fund->driver->saldo,
                'is_tetap' => (bool) $fund->driver->is_tetap,
                'user_id' => $fund->driver->user_id,
            ] : null,
            'payment_account' => $fund->relationLoaded('paymentAccount') && $fund->paymentAccount ? [
                'id' => $fund->paymentAccount->id,
                'nama_bank' => $fund->paymentAccount->nama_bank,
                'nomor_rekening' => $fund->paymentAccount->nomor_rekening,
                'atas_nama' => $fund->paymentAccount->atas_nama,
            ] : null,
            'creator' => $fund->relationLoaded('creator') && $fund->creator ? [
                'id' => $fund->creator->id,
                'name' => $fund->creator->name,
                'role' => $fund->creator->role,
            ] : null,
            'booking_detail' => $fund->relationLoaded('bookingDetail') && $fund->bookingDetail ? [
                'id' => $fund->bookingDetail->id,
                'tgl_sewa' => $fund->bookingDetail->tgl_sewa,
                'tgl_kembali' => $fund->bookingDetail->tgl_kembali,
                'detail_type' => $fund->bookingDetail->detail_type,
                'unit' => $fund->bookingDetail->relationLoaded('unit') && $fund->bookingDetail->unit ? [
                    'id' => $fund->bookingDetail->unit->id,
                    'no_polisi' => $fund->bookingDetail->unit->no_polisi,
                    'merk' => $fund->bookingDetail->unit->merk,
                    'tipe' => $fund->bookingDetail->unit->tipe,
                ] : null,
            ] : null,
            'expenses' => $fund->relationLoaded('expenses')
                ? DriverOperationalExpenseResource::collection($fund->expenses)
                : [],
            'summary' => [
                'approved_expense_total' => (int) $fund->approvedExpenseTotal(),
                'approved_reimbursement_total' => (int) $fund->approvedExpenseTotal(),
                'approved_return_total' => (int) $fund->approvedReturnTotal(),
                'pending_review_count' => (int) $fund->pendingReviewCount(),
                'pending_driver_review_count' => (int) $fund->pendingDriverReviewCount(),
                'remaining_amount' => (int) $fund->remainingAmount(),
            ],
        ];
    }
}
