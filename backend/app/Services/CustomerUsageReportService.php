<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerUsageReportService
{
    private function baseQuery(array $filters)
    {
        $user = Auth::user();

        $q = DB::table('bookings as b')
            ->join('customers as c', 'c.id', '=', 'b.customer_id')
            ->whereNull('b.deleted_at')
            ->where('b.tenant_id', $user->tenant_id)
            ->where('b.status', '!=', 'batal');

        if ($user->role !== 'superadmin') {
            $q->where('b.branch_id', $user->branch_id);
        }

        if (!empty($filters['status'])) {
            $q->where('c.status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q->where(fn ($w) => $w
                ->where('c.nama', 'like', '%' . $filters['search'] . '%')
                ->orWhere('c.kontak_1', 'like', '%' . $filters['search'] . '%'));
        }

        return $q;
    }

    public function report(array $filters = []): array
    {
        $base = $this->baseQuery($filters);

        $summary = (clone $base)->selectRaw(
            'COUNT(DISTINCT c.id) as total_pelanggan, COUNT(*) as total_sewa'
        )->first();

        $paginator = $base
            ->groupBy('c.id', 'c.nama', 'c.status', 'c.kontak_1')
            ->selectRaw(
                "c.id as customer_id, c.nama, c.status, c.kontak_1,
                 COALESCE(GROUP_CONCAT(DISTINCT b.kota ORDER BY b.kota SEPARATOR ', '), '-') as kota,
                 COUNT(*) as jumlah_sewa"
            )
            ->orderByDesc('jumlah_sewa')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'customer_id' => (int) $r->customer_id,
                'nama' => $r->nama ?? '-',
                'status' => $r->status ?? '-',
                'kontak_1' => $r->kontak_1 ?? '-',
                'kota' => $r->kota,
                'jumlah_sewa' => (int) $r->jumlah_sewa,
            ])->all(),
            'summary' => [
                'total_pelanggan' => (int) $summary->total_pelanggan,
                'total_sewa' => (int) $summary->total_sewa,
            ],
            'meta' => $this->meta($paginator),
        ];
    }

    public function transactions(array $filters = []): array
    {
        $paginator = $this->baseQuery($filters)
            ->leftJoin('booking_details as bd', fn ($j) => $j->on('bd.booking_id', '=', 'b.id')->whereNull('bd.deleted_at'))
            ->leftJoin('units as u', 'u.id', '=', 'bd.unit_id')
            ->where('b.customer_id', $filters['customer_id'])
            ->groupBy('b.id', 'b.kode_booking', 'b.status', 'b.kota', 'b.created_at')
            ->selectRaw(
                "b.id, b.kode_booking, b.status, b.kota,
                 MIN(bd.tgl_sewa) as tgl_sewa, MAX(bd.tgl_kembali) as tgl_kembali,
                 COALESCE(GROUP_CONCAT(DISTINCT u.no_polisi SEPARATOR ', '), '-') as units"
            )
            ->orderByDesc('b.created_at')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => collect($paginator->items())->map(fn ($r) => [
                'id' => (int) $r->id,
                'kode_booking' => $r->kode_booking,
                'status' => $r->status,
                'kota' => $r->kota ?? '-',
                'tgl_sewa' => $r->tgl_sewa,
                'tgl_kembali' => $r->tgl_kembali,
                'units' => $r->units,
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
