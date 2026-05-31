<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UnitUsageReportService
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
            ->join('units as u', 'u.id', '=', 'bd.unit_id')
            ->leftJoin('rental_owners as ro', 'ro.id', '=', 'u.rental_owner_id')
            ->leftJoin('cities as c', 'c.id', '=', 'u.city_id')
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
        if (!empty($filters['city_id'])) {
            $q->where('u.city_id', $filters['city_id']);
        }
        if (!empty($filters['rental_owner_id'])) {
            $q->where('u.rental_owner_id', $filters['rental_owner_id']);
        }
        if (!empty($filters['search'])) {
            $q->where('u.no_polisi', 'like', '%' . $filters['search'] . '%');
        }

        return $q;
    }

    public function report(array $filters = []): array
    {
        $days = $this->days();
        $base = $this->baseQuery($filters);

        $summary = (clone $base)->selectRaw(
            "COUNT(DISTINCT u.id) as total_unit,
             COUNT(*) as total_transaksi,
             COALESCE(SUM($days), 0) as total_hari,
             COALESCE(SUM(COALESCE(bd.modal_mobil, 0) * $days), 0) as total_pendapatan"
        )->first();

        $paginator = $base
            ->groupBy('u.id', 'u.tipe', 'u.no_polisi', 'ro.nama', 'c.nama')
            ->selectRaw(
                "u.id as unit_id, u.tipe, u.no_polisi,
                 COALESCE(ro.nama, 'DRENT') as pemilik,
                 COALESCE(c.nama, '-') as kota,
                 COUNT(*) as jumlah_transaksi,
                 COALESCE(SUM($days), 0) as total_hari,
                 COALESCE(SUM(COALESCE(bd.modal_mobil, 0) * $days), 0) as total_pendapatan"
            )
            ->orderByDesc('total_pendapatan')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'unit_id' => (int) $r->unit_id,
                'tipe' => $r->tipe ?? '-',
                'no_polisi' => $r->no_polisi ?? '-',
                'pemilik' => $r->pemilik,
                'kota' => $r->kota,
                'jumlah_transaksi' => (int) $r->jumlah_transaksi,
                'total_hari' => (int) $r->total_hari,
                'total_pendapatan' => (int) $r->total_pendapatan,
            ])->all(),
            'summary' => [
                'total_unit' => (int) $summary->total_unit,
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
            ->leftJoin('customers as cust', 'cust.id', '=', 'b.customer_id')
            ->where('bd.unit_id', $filters['unit_id'])
            ->orderByDesc('bd.tgl_sewa')
            ->selectRaw(
                "bd.id, b.kode_booking,
                 COALESCE(cust.nama, '-') as customer,
                 bd.tgl_sewa, bd.tgl_kembali,
                 $days as hari,
                 (COALESCE(bd.modal_mobil, 0) * $days) as pendapatan"
            )
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'id' => (int) $r->id,
                'kode_booking' => $r->kode_booking,
                'customer' => $r->customer,
                'tgl_sewa' => $r->tgl_sewa,
                'tgl_kembali' => $r->tgl_kembali,
                'hari' => (int) $r->hari,
                'pendapatan' => (int) $r->pendapatan,
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
