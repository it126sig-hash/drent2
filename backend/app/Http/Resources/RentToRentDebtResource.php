<?php

namespace App\Http\Resources;

use App\Services\RentToRentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentToRentDebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(RentToRentService::class);
        $detail = $this->bookingDetail;
        $unit = $detail?->unit;
        $activeItem = $this->relationLoaded('billItems') ? $this->billItems
            ->filter(fn($item) => $item->bill && ! in_array($item->bill->status, ['void'], true))
            ->sortByDesc('created_at')
            ->first() : null;
        $totalAmount = (int) $this->cached_total_amount;
        $paidAmount = (int) $this->cached_paid_amount;

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking_detail_id' => $this->booking_detail_id,
            'kode_booking' => $this->booking?->kode_booking,
            'status' => $this->cached_payment_status ?: $this->status,
            'raw_status' => $this->status,
            'amount_override' => $this->amount_override,
            'default_amount' => $service->currentAmount($this->resource),
            'selling_price' => $service->sellingPrice($this->resource),
            'pricing_mode' => $detail?->pricing_mode,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, $totalAmount - $paidAmount),
            'can_edit_amount' => ! $activeItem,
            'rental_owner' => [
                'id' => $this->rentalOwner?->id,
                'nama' => $this->rentalOwner?->nama,
                'kontak_1' => $this->rentalOwner?->kontak_1,
                'bank' => $this->rentalOwner?->bank,
                'no_rek' => $this->rentalOwner?->no_rek,
                'atas_nama' => $this->rentalOwner?->atas_nama,
            ],
            'booking' => [
                'id' => $this->booking?->id,
                'kode_booking' => $this->booking?->kode_booking,
                'status' => $this->booking?->status,
                'customer_name' => $this->booking?->customer?->nama,
                'tujuan' => $this->booking?->tujuan,
            ],
            'unit' => [
                'id' => $unit?->id,
                'name' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: '-',
                'no_polisi' => $unit?->no_polisi,
                'paket_sewa' => $detail?->paket_sewa,
                'lama_sewa' => $detail?->lama_sewa,
                'modal_1_hari' => $unit?->modal_1_hari,
                'modal_1_minggu' => $unit?->modal_1_minggu,
                'modal_1_bulan' => $unit?->modal_1_bulan,
                'modal_all_in' => $unit?->modal_all_in,
                'modal_all_in_1_minggu' => $unit?->modal_all_in_1_minggu,
                'modal_all_in_1_bulan' => $unit?->modal_all_in_1_bulan,
                'harga_1_hari' => $unit?->harga_1_hari,
                'harga_1_minggu' => $unit?->harga_1_minggu,
                'harga_1_bulan' => $unit?->harga_1_bulan,
                'harga_all_in' => $unit?->harga_all_in,
                'harga_all_in_1_minggu' => $unit?->harga_all_in_1_minggu,
                'harga_all_in_1_bulan' => $unit?->harga_all_in_1_bulan,
                'tgl_sewa' => $detail?->tgl_sewa,
                'tgl_kembali' => $detail?->tgl_kembali,
                'detail_type' => $detail?->detail_type,
            ],
            'bill' => $activeItem?->bill ? [
                'id' => $activeItem->bill->id,
                'number' => $activeItem->bill->bill_number,
                'status' => $activeItem->bill->status,
                'amount' => (int) $activeItem->amount,
                'generated_at' => $activeItem->bill->generated_at?->toISOString(),
                'sent_at' => $activeItem->bill->sent_at?->toISOString(),
            ] : null,
            'payments' => $this->relationLoaded('paymentAllocations')
                ? $this->paymentAllocations
                    ->sortByDesc(fn($allocation) => $allocation->payment?->paid_at?->timestamp ?? 0)
                    ->map(fn($allocation) => [
                        'id' => $allocation->id,
                        'payment_id' => $allocation->payment?->id,
                        'amount' => (int) $allocation->amount,
                        'status' => $allocation->payment?->status ?? 'active',
                        'paid_at' => $allocation->payment?->paid_at?->toISOString(),
                        'voided_at' => $allocation->payment?->voided_at?->toISOString(),
                        'void_reason' => $allocation->payment?->void_reason,
                        'void_requested_at' => $allocation->payment?->void_requested_at?->toISOString(),
                        'void_approved_at' => $allocation->payment?->void_approved_at?->toISOString(),
                        'bill_number' => $allocation->payment?->bill?->bill_number,
                        'payment_account_name' => $allocation->payment?->paymentAccount
                            ? trim($allocation->payment->paymentAccount->nama_bank.' '.$allocation->payment->paymentAccount->nomor_rekening)
                            : null,
                    ])
                    ->values()
                : [],
            'pending_amount_request' => $this->pendingAmountRequest ? [
                'id' => $this->pendingAmountRequest->id,
                'requested_amount_override' => $this->pendingAmountRequest->requested_amount_override,
                'reason' => $this->pendingAmountRequest->reason,
                'status' => $this->pendingAmountRequest->status,
                'requested_at' => $this->pendingAmountRequest->requested_at?->toISOString(),
                'requested_by' => $this->pendingAmountRequest->requestedBy ? [
                    'id' => $this->pendingAmountRequest->requestedBy->id,
                    'name' => $this->pendingAmountRequest->requestedBy->name,
                ] : null,
            ] : null,
            'can_request_amount_change' => ! $this->pendingAmountRequest && ! $activeItem && $this->status !== 'cancelled' && ! in_array($this->cached_payment_status ?: $this->status, ['paid', 'paid_manual'], true),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
