<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBookingProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'customer_name' => $this->customer?->nama,
            'customer_phone' => $this->maskContact($this->customer?->kontak_1),
            'customer_address' => $this->maskContact($this->customer?->alamat),
            'timeline' => $this->bookingDetails
                ->sortBy('id')
                ->map(function ($detail) {
                    $unit = $detail->unit;

                    return [
                        'detail_type' => $detail->detail_type,
                        'status' => $detail->status,
                        'vehicle_name' => trim(implode(' ', array_filter([$unit?->merk, $unit?->tipe]))) ?: $detail->unit_placeholder,
                        'rental_start_date' => $detail->tgl_sewa,
                        'rental_end_date' => $detail->tgl_kembali,
                        'lama_sewa' => $detail->lama_sewa,
                        'costs' => $detail->costs->map(fn($cost) => [
                            'label' => $cost->label,
                            'type' => $cost->type,
                            'amount' => (int) $cost->amount,
                            'is_additional' => (bool) $cost->is_additional,
                        ])->values(),
                    ];
                })
                ->values(),
        ];
    }

    private function maskContact(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 3) . str_repeat('*', $length - 5) . mb_substr($value, -2);
    }
}
