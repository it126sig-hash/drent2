<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill booking_details.modal_mobil for rows that were created before
 * the column was added (or while it was missing from BookingDetail::$fillable
 * and silently dropped on mass-assignment).
 *
 * Snapshot is computed from the unit master using the same logic as
 * BookingService::assignDetail() / handleBooking():
 *   - pricing_mode = 'all_in' AND no pricing_package_id => modal_all_in[_paket]
 *   - otherwise                                         => modal_1_[paket]
 *
 * Only rows with unit_id set and modal_mobil <= 0 are touched. Existing
 * non-zero snapshots are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('booking_details as bd')
            ->join('units as u', 'u.id', '=', 'bd.unit_id')
            ->whereNotNull('bd.unit_id')
            ->where(function ($q) {
                $q->whereNull('bd.modal_mobil')->orWhere('bd.modal_mobil', '<=', 0);
            })
            ->select([
                'bd.id',
                'bd.paket_sewa',
                'bd.pricing_mode',
                'bd.pricing_package_id',
                'u.modal_1_hari',
                'u.modal_1_minggu',
                'u.modal_1_bulan',
                'u.modal_all_in',
                'u.modal_all_in_1_minggu',
                'u.modal_all_in_1_bulan',
            ])
            ->get();

        foreach ($rows as $row) {
            $paket = strtolower((string) ($row->paket_sewa ?? 'harian'));
            $isAllIn = $row->pricing_mode === 'all_in';
            $hasPackage = ! empty($row->pricing_package_id);

            if ($isAllIn && ! $hasPackage) {
                $modal = match ($paket) {
                    'mingguan' => (int) ($row->modal_all_in_1_minggu ?? 0),
                    'bulanan'  => (int) ($row->modal_all_in_1_bulan ?? 0),
                    default    => (int) ($row->modal_all_in ?? 0),
                };
            } else {
                $modal = match ($paket) {
                    'mingguan' => (int) ($row->modal_1_minggu ?? 0),
                    'bulanan'  => (int) ($row->modal_1_bulan ?? 0),
                    default    => (int) ($row->modal_1_hari ?? 0),
                };
            }

            if ($modal <= 0) {
                continue;
            }

            DB::table('booking_details')
                ->where('id', $row->id)
                ->update(['modal_mobil' => $modal]);
        }
    }

    public function down(): void
    {
        // Non-reversible data backfill. Resetting modal_mobil to 0 would
        // discard snapshots that may have been edited after the backfill.
    }
};
