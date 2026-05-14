<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'booking_id'            => $this->booking_id,
            'payment_account_id'    => $this->payment_account_id,
            'amount'                => (int) $this->amount,
            'payment_type'          => $this->payment_type,
            'status'                => $this->status ?? 'active',
            'catatan'               => $this->catatan,
            'void_reason'           => $this->void_reason,
            'void_requested_at'     => $this->void_requested_at?->toISOString(),
            'void_approved_at'      => $this->void_approved_at?->toISOString(),
            'void_rejected_at'      => $this->void_rejected_at?->toISOString(),
            'void_rejection_note'   => $this->void_rejection_note,
            'paid_at'               => $this->paid_at?->toISOString(),
            'reallocated_from_id'   => $this->reallocated_from_id,
            'created_by'            => $this->created_by,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
            'payment_account'       => $this->whenLoaded('paymentAccount', fn() => [
                'id'        => $this->paymentAccount->id,
                'nama_bank' => $this->paymentAccount->nama_bank,
                'nomor_rekening' => $this->paymentAccount->nomor_rekening,
                'atas_nama' => $this->paymentAccount->atas_nama,
            ]),
            'creator'               => $this->whenLoaded('creator', fn() => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'void_requester'        => $this->whenLoaded('voidRequester', fn() => $this->voidRequester ? [
                'id'   => $this->voidRequester->id,
                'name' => $this->voidRequester->name,
            ] : null),
            'void_approver'         => $this->whenLoaded('voidApprover', fn() => $this->voidApprover ? [
                'id'   => $this->voidApprover->id,
                'name' => $this->voidApprover->name,
            ] : null),
            'void_rejecter'         => $this->whenLoaded('voidRejecter', fn() => $this->voidRejecter ? [
                'id'   => $this->voidRejecter->id,
                'name' => $this->voidRejecter->name,
            ] : null),
            'reallocated_from'      => $this->whenLoaded('reallocatedFrom', fn() => $this->reallocatedFrom ? [
                'id'           => $this->reallocatedFrom->id,
                'booking_id'   => $this->reallocatedFrom->booking_id,
                'amount'       => (int) $this->reallocatedFrom->amount,
                'payment_type' => $this->reallocatedFrom->payment_type,
            ] : null),
        ];
    }
}
