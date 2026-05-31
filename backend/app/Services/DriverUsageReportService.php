<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverUsageReportService
{
    private function days(): string
    {
        return 'GREATEST(DATEDIFF(bd.tgl_kembali, bd.tgl_sewa) + 1, 1)';
    }

    private function baseQuery(array $filters)
    {
        $user = Auth::user();

        $q = DB::table('booking_details as bd')
            ->join('bookings as b', 'b.id', '=', 'bd.booking_id')
            ->leftJoin('drivers as d', 'd.id', '=', 'bd.driver_id')
            ->leftJoin(DB::raw("(SELECT booking_detail_id, SUM(amount) AS gaji FROM driver_operational_funds WHERE fund_type = 'salary' AND status = 'closed' GROUP BY booking_detail_id) as sf"), 'sf.booking_detail_id', '=', 'bd.id')
            ->whereNull('bd.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('b.tenant_id', $user->tenant_id)
            ->where('bd.status', '!=', 'batal')
            ->whereNotNull('bd.tgl_sewa')
            ->whereNotNull('bd.tgl_kembali');

        if ($user->role !== 'superadmin') {
            $q->where('b.branch_id', $user->branch_id);
        }

        if (($filters['mode'] ?? 'all_in') === 'all_in') {
            $q->where('bd.pricing_mode', 'all_in')->whereNull('bd.pricing_package_id');
        } else {
            $q->where(fn ($w) => $w
                ->where('bd.pricing_mode', '!=', 'all_in')
                ->orWhereNull('bd.pricing_mode')
                ->orWhereNotNull('bd.pricing_package_id'));
        }

        if (!empty($filters['date_from'])) {
            $q->whereDate('bd.tgl_sewa', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('bd.tgl_sewa', '<=', $filters['date_to']);
        }
        if (!empty($filters['kota'])) {
            $q->where('b.kota', $filters['kota']);
        }
        if (!empty($filters['search'])) {
            $q->where(fn ($w) => $w
                ->where('d.nama', 'like', '%' . $filters['search'] . '%')
                ->orWhere('d.kontak_1', 'like', '%' . $filters['search'] . '%'));
        }

        return $q;
    }

    public function report(array $filters = []): array
    {
        $days = $this->days();
        $base = $this->baseQuery($filters);

        $summary = (clone $base)->selectRaw(
            "COUNT(DISTINCT d.id) as total_driver,
             COUNT(*) as total_transaksi,
             COALESCE(SUM($days), 0) as total_hari,
             COALESCE(SUM(sf.gaji), 0) as total_pendapatan"
        )->first();

        $paginator = $base
            ->groupBy('d.id', 'd.nama', 'd.kontak_1', 'd.is_tetap')
            ->selectRaw(
                "COALESCE(d.id, 0) as driver_id,
                 COALESCE(d.nama, 'Tanpa Driver / Lepas Kunci') as nama,
                 d.kontak_1,
                 COALESCE(GROUP_CONCAT(DISTINCT b.kota ORDER BY b.kota SEPARATOR ', '), '-') as kota,
                 d.is_tetap,
                 COUNT(*) as jumlah_transaksi,
                 COALESCE(SUM($days), 0) as total_hari,
                 COALESCE(SUM(sf.gaji), 0) as total_pendapatan"
            )
            ->orderByDesc('total_pendapatan')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'driver_id' => (int) $r->driver_id,
                'nama' => $r->nama ?? '-',
                'kontak_1' => $r->kontak_1 ?? '-',
                'kota' => $r->kota,
                'is_tetap' => (bool) $r->is_tetap,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_hari' => (int) $r->total_hari,
                'total_pendapatan' => (int) $r->total_pendapatan,
            ])->all(),
            'summary' => [
                'total_driver' => (int) $summary->total_driver,
                'total_transaksi' => (int) $summary->total_transaksi,
                'total_hari' => (int) $summary->total_hari,
                'total_pendapatan' => (int) $summary->total_pendapatan,
            ],
            'meta' => $this->meta($paginator),
        ];
    }

    public function transactions(array $filters = []): array
    {
        $days = $this->days();

        $paginator = $this->baseQuery($filters)
            ->leftJoin('units as u', 'u.id', '=', 'bd.unit_id')
            ->leftJoin('customers as cust', 'cust.id', '=', 'b.customer_id')
            ->when((int) ($filters['driver_id'] ?? 0) === 0,
                fn ($q) => $q->whereNull('bd.driver_id'),
                fn ($q) => $q->where('bd.driver_id', $filters['driver_id']))
            ->orderByDesc('bd.tgl_sewa')
            ->selectRaw(
                "bd.id, b.kode_booking, b.kota,
                 COALESCE(cust.nama, '-') as customer,
                 COALESCE(u.no_polisi, '-') as no_polisi,
                 bd.tgl_sewa, bd.tgl_kembali,
                 $days as hari,
                 COALESCE(sf.gaji, 0) as gaji"
            )
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'id' => (int) $r->id,
                'kode_booking' => $r->kode_booking,
                'customer' => $r->customer,
                'no_polisi' => $r->no_polisi,
                'kota' => $r->kota,
                'tgl_sewa' => $r->tgl_sewa,
                'tgl_kembali' => $r->tgl_kembali,
                'hari' => (int) $r->hari,
                'gaji' => (int) $r->gaji,
            ])->all(),
            'meta' => $this->meta($paginator),
        ];
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
