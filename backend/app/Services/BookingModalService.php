<?php

namespace App\Services;

use App\Models\Unit;

class BookingModalService
{
    /**
     * Resolve modal mobil per periode untuk sebuah booking detail.
     * Prioritas: nilai manual dari request, lalu fallback ke master unit
     * sesuai paket sewa & pricing mode.
     */
    public function resolve(array $data, ?string $paketFallback = null): int
    {
        if (array_key_exists('modal_mobil', $data) && $data['modal_mobil'] !== null) {
            return max(0, (int) $data['modal_mobil']);
        }

        if (empty($data['unit_id'])) {
            return 0;
        }

        $unit = Unit::find($data['unit_id']);

        if (! $unit) {
            return 0;
        }

        $paket = strtolower((string) ($data['paket_sewa'] ?? $paketFallback ?? ''));
        $isAllIn = ($data['pricing_mode'] ?? 'non_all_in') === 'all_in' && empty($data['pricing_package_id']);

        $modal = $isAllIn
            ? match ($paket) {
                'mingguan' => $unit->modal_all_in_1_minggu,
                'bulanan'  => $unit->modal_all_in_1_bulan,
                default    => $unit->modal_all_in,
            }
            : match ($paket) {
                'mingguan' => $unit->modal_1_minggu,
                'bulanan'  => $unit->modal_1_bulan,
                default    => $unit->modal_1_hari,
            };

        return max(0, (int) $modal);
    }
}
