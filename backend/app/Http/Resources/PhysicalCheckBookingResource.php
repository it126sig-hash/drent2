<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhysicalCheckBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $this->displayDetail();
        $departure = $this->latestCheck('departure');
        $return = $this->latestCheck('return');

        return [
            'id' => $this->id,
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'customer' => [
                'id' => $this->customer?->id,
                'nama' => $this->customer?->nama,
                'status' => $this->customer?->status,
            ],
            'vehicle' => [
                'title' => $detail?->unit
                    ? trim(($detail->unit->merk ?? '') . ' ' . ($detail->unit->tipe ?? ''))
                    : ($detail?->unit_placeholder ?? 'Belum ditentukan'),
                'no_polisi' => $detail?->unit?->no_polisi,
                'owner' => $detail?->unit?->rentalOwner?->nama,
            ],
            'rental' => [
                'tgl_sewa' => $detail?->tgl_sewa,
                'tgl_kembali' => $detail?->tgl_kembali,
            ],
            'checks' => [
                'departure' => $this->checkPayload($departure),
                'return' => $this->checkPayload($return),
            ],
            'eligibility' => [
                'departure' => $this->eligibility('departure', $detail),
                'return' => $this->eligibility('return', $detail),
            ],
        ];
    }

    private function displayDetail()
    {
        $details = $this->bookingDetails ?? collect();

        return $details->firstWhere('status', 'aktif')
            ?? $details->firstWhere('detail_type', 'initial')
            ?? $details->firstWhere('status', 'draft')
            ?? $details->last();
    }

    private function latestCheck(string $type)
    {
        if (! $this->relationLoaded('physicalChecks')) {
            return null;
        }

        return $this->physicalChecks
            ->where('type', $type)
            ->sortByDesc('id')
            ->first();
    }

    private function checkPayload($check): array
    {
        return [
            'id' => $check?->id,
            'status' => $check?->status ?? 'not_requested',
            'requested_at' => $check?->requested_at?->toISOString(),
            'inspected_at' => $check?->inspected_at?->toISOString(),
            'skipped_at' => $check?->skipped_at?->toISOString(),
        ];
    }

    private function eligibility(string $type, $detail): array
    {
        if (! $detail) {
            return ['allowed' => false, 'reason' => 'Detail kendaraan belum tersedia.'];
        }

        if ($type === 'departure' && $this->status !== 'waiting_list') {
            return ['allowed' => false, 'reason' => 'Cek keberangkatan hanya untuk status Waiting List.'];
        }

        if ($type === 'return' && $this->status !== 'rental_unit') {
            return ['allowed' => false, 'reason' => 'Cek kembali hanya untuk status Rental Unit.'];
        }

        $dateColumn = $type === 'departure' ? $detail->tgl_sewa : $detail->tgl_kembali;
        if (! $dateColumn) {
            return ['allowed' => false, 'reason' => 'Tanggal sewa/kembali belum tersedia.'];
        }

        $target = Carbon::parse($dateColumn)->startOfDay();
        $start = $type === 'departure' ? $target->copy()->subDay() : $target->copy();
        $end = $type === 'departure' ? $target->copy()->endOfDay() : $target->copy()->addDay()->endOfDay();
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        if ($today->lt($start->copy()->startOfDay())) {
            return [
                'allowed' => false,
                'reason' => $type === 'departure'
                    ? 'Cek keberangkatan baru bisa dilakukan H-1 atau hari H.'
                    : 'Cek kembali baru bisa dilakukan pada tanggal kembali.',
                'window_start' => $start->toDateString(),
                'window_end' => $end->toDateString(),
            ];
        }

        if ($today->gt($end->copy()->startOfDay())) {
            return [
                'allowed' => false,
                'reason' => $type === 'departure'
                    ? 'Window cek keberangkatan sudah lewat.'
                    : 'Window cek kembali hanya tanggal kembali sampai H+1.',
                'window_start' => $start->toDateString(),
                'window_end' => $end->toDateString(),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'window_start' => $start->toDateString(),
            'window_end' => $end->toDateString(),
        ];
    }
}
