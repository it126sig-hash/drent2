<?php

namespace App\Http\Resources;

use App\Services\RentToRentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentToRentBillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(RentToRentService::class);
        $paidAmount = $service->billPaidAmount($this->resource);

        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'status' => $this->status,
            'public_token' => $this->public_token,
            'public_path' => $this->public_token ? "/rent-to-rent/{$this->public_token}" : null,
            'public_url' => $this->public_token ? url("/rent-to-rent/{$this->public_token}") : null,
            'pdf_url' => $this->public_token ? url("/api/v1/public/rent-to-rent-bills/{$this->public_token}/pdf") : null,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(0, (int) $this->total_amount - $paidAmount),
            'generated_at' => $this->generated_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'void_requested_at' => $this->void_requested_at?->toISOString(),
            'void_approved_at' => $this->void_approved_at?->toISOString(),
            'void_rejected_at' => $this->void_rejected_at?->toISOString(),
            'void_rejection_note' => $this->void_rejection_note,
            'void_requester' => $this->whenLoaded('voidRequester', fn() => $this->voidRequester ? [
                'id' => $this->voidRequester->id,
                'name' => $this->voidRequester->name,
            ] : null),
            'void_approver' => $this->whenLoaded('voidApprover', fn() => $this->voidApprover ? [
                'id' => $this->voidApprover->id,
                'name' => $this->voidApprover->name,
            ] : null),
            'void_rejecter' => $this->whenLoaded('voidRejecter', fn() => $this->voidRejecter ? [
                'id' => $this->voidRejecter->id,
                'name' => $this->voidRejecter->name,
            ] : null),
            'rental_owner' => [
                'id' => $this->rentalOwner?->id,
                'nama' => $this->rentalOwner?->nama,
                'kontak_1' => $this->rentalOwner?->kontak_1,
                'bank' => $this->rentalOwner?->bank,
                'no_rek' => $this->rentalOwner?->no_rek,
                'atas_nama' => $this->rentalOwner?->atas_nama,
            ],
            'items' => $this->whenLoaded('items', fn() => $this->items->map(function ($item) {
                $debt = $item->debt;
                $detail = $debt?->bookingDetail;
                $unit = $detail?->unit;

                return [
                    'id' => $item->id,
                    'debt_id' => $item->rent_to_rent_debt_id,
                    'booking_detail_id' => $item->booking_detail_id,
                    'amount' => (int) $item->amount,
                    'paid_amount' => (int) $item->allocations
                        ->filter(fn($allocation) => ($allocation->payment?->status ?? 'active') !== 'voided')
                        ->sum('amount'),
                    'kode_booking' => $debt?->booking?->kode_booking,
                    'customer_name' => $debt?->booking?->customer?->nama,
                    'tujuan' => $debt?->booking?->tujuan,
                    'unit_name' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: '-',
                    'unit_plate' => $unit?->no_polisi,
                    'rental_start_date' => $detail?->tgl_sewa,
                    'rental_end_date' => $detail?->tgl_kembali,
                ];
            })->values()),
            'payments' => $this->whenLoaded('payments', fn() => $this->payments
                ->sortByDesc(fn($payment) => $payment->paid_at?->timestamp ?? 0)
                ->map(fn($payment) => [
                    'id' => $payment->id,
                    'payment_account_id' => $payment->payment_account_id,
                    'payment_account_name' => $payment->paymentAccount
                        ? trim($payment->paymentAccount->nama_bank.' '.$payment->paymentAccount->nomor_rekening)
                        : null,
                    'amount' => (int) $payment->amount,
                    'status' => $payment->status ?? 'active',
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'voided_at' => $payment->voided_at?->toISOString(),
                    'created_by_name' => $payment->creator?->name,
                    'created_at' => $payment->created_at?->toISOString(),
                ])
                ->values()),
        ];
    }
}
