<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionListResource;
use App\Models\Booking;
use App\Models\DriverOperationalFund;
use App\Models\RentToRentPaymentAllocation;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(private PermissionService $permissionService) {}

    /**
     * Display a listing of transactions.
     *
     * GET /api/v1/transactions
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.transaction'),
            403
        );

        $statuses = $this->arrayFilter($request->input('status'));
        // If status filter is empty, default to waiting_list, rental_unit, selesai, batal, reschedule to match requirements
        if (empty($statuses)) {
            $statuses = ['waiting_list', 'rental_unit', 'selesai', 'batal', 'reschedule'];
        }

        $query = Booking::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->role === 'cs', fn($q) => $q->where('handled_by', $user->id))
            // Total uang masuk dari konsumen (untuk transaksi yang dibatalkan) — ini selalu per-booking
            // karena pembayaran konsumen tidak tercatat per booking_detail.
            ->withSum(['payments as total_payments_in' => fn($q) => $q->where('status', '!=', 'voided')], 'amount')
            ->with([
                'customer:id,nama,status',
                'bookingDetails.unit.rentalOwner:id,nama,is_owner',
                'bookingDetails.createdBy:id,name',
                'bookingDetails.costs.costType',
                // Dimuat penuh (bukan withSum) supaya bisa dihitung independen per booking_detail_id.
                'rentToRentDebts' => fn($q) => $q->where('status', '!=', 'cancelled'),
                'operationalFunds' => fn($q) => $q->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed']),
                'operationalExpenses' => fn($q) => $q->whereIn('status', ['approved', 'void_requested']),
                'fleetCardFunds' => fn($q) => $q->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed']),
                'fleetCardExpenses' => fn($q) => $q->whereIn('status', ['approved', 'void_requested']),
                'handledBy:id,name',
                'cancellation',
            ])
            ->whereIn('status', $statuses);

        // Filters
        if ($request->filled('date_from')) {
            $query->whereHas('bookingDetails', fn($detail) => $detail->whereDate('tgl_sewa', '>=', $request->date_from));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('bookingDetails', fn($detail) => $detail->whereDate('tgl_sewa', '<=', $request->date_to));
        }
        if ($request->filled('kota')) {
            $query->where('kota', $request->kota);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_booking', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($c) => $c->where('nama', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('handled_by') && $user->role !== 'cs') {
            $query->where('handled_by', $request->handled_by);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->integer('per_page', 15);
        $bookings = $query->paginate($perPage);

        // Setiap booking_detail (initial/extend/rolling) ditampilkan sebagai baris sendiri,
        // dengan kode_booking yang sama, supaya histori perpanjangan/rolling terlihat di list.
        $rows = collect();
        foreach ($bookings as $booking) {
            $details = $booking->bookingDetails->sortBy('created_at')->values();
            foreach ($details as $detail) {
                $rows->push((new TransactionListResource($booking))->forDetail($detail, $details)->resolve($request));
            }
        }

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'per_page'     => $bookings->perPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }

    /**
     * Display the specified transaction detail (for modal).
     *
     * GET /api/v1/transactions/{booking}
     */
    public function show(Booking $booking)
    {
        $user = auth()->user();
        abort_unless(
            $user->role === 'superadmin' ||
            $this->permissionService->hasPermission($user, 'finance.transaction'),
            403
        );

        // Security check
        abort_unless($booking->tenant_id === $user->tenant_id, 403);
        if ($user->role !== 'superadmin') {
            abort_unless($booking->branch_id === $user->branch_id, 403);
        }
        if ($user->role === 'cs') {
            abort_unless($booking->handled_by === $user->id, 403);
        }

        $booking->load([
            'customer:id,nama,status,kota',
            'bookingDetails.unit.rentalOwner',
            'bookingDetails.costs.costType',
            'payments.paymentAccount',
            'payments.creator',
            'operationalFunds.paymentAccount',
            'operationalExpenses.costType',
            'operationalExpenses.fund',
            'fleetCardFunds.fleetCard',
            'fleetCardExpenses.costType',
            'fleetCardExpenses.fund',
            'rentToRentDebts.rentalOwner',
        ]);

        // Build unified history
        $history = [];

        // 1. Pemasukan — Booking Payments
        foreach ($booking->payments as $p) {
            if ($p->status !== 'voided') {
                $history[] = [
                    'id'          => 'income-' . $p->id,
                    'date'        => $p->paid_at?->toISOString() ?? $p->created_at?->toISOString(),
                    'category'    => 'pembayaran_booking',
                    'type'        => 'pemasukan',
                    'description' => ($p->payment_type === 'dp' ? 'DP' : 'Pelunasan')
                        . ': ' . ($p->catatan ?? 'Pembayaran rental')
                        . ($p->paymentAccount ? ' (' . $p->paymentAccount->nama_bank . ')' : ''),
                    'amount'      => (int) $p->amount,
                ];
            }
        }

        // 2. Pengeluaran — Dana Operasional diserahkan ke Driver (DriverOperationalFund)
        //    Ini adalah uang kas yang benar-benar keluar dari perusahaan ke driver.
        foreach ($booking->operationalFunds as $fund) {
            if (in_array($fund->status, ['pending_driver_acceptance', 'accepted', 'closed'], true)) {
                $isSalary = ($fund->fund_type ?? 'operational') === 'salary';
                $history[] = [
                    'id'          => 'fund-' . $fund->id,
                    'date'        => $fund->accepted_at?->toISOString() ?? $fund->created_at?->toISOString(),
                    'category'    => $isSalary ? 'gaji_driver' : 'dana_operasional',
                    'type'        => 'pengeluaran',
                    'description' => ($isSalary ? 'Pembayaran Gaji Driver' : 'Dana Operasional → Driver')
                        . ($fund->notes ? ': ' . $fund->notes : '')
                        . ($fund->paymentAccount ? ' (' . $fund->paymentAccount->nama_bank . ')' : ''),
                    'amount'      => -(int) $fund->amount,
                ];
            }
        }

        // 3. Pemasukan kembali — Sisa Dana Dikembalikan Driver (DriverOperationalExpense type='return', approved)
        //    Driver mengembalikan sisa uang → mengurangi total pengeluaran operasional.
        foreach ($booking->operationalExpenses as $e) {
            if (in_array($e->status, ['approved', 'void_requested'], true) && $e->type === 'return') {
                $history[] = [
                    'id'          => 'return-' . $e->id,
                    'date'        => $e->reviewed_at?->toISOString() ?? $e->created_at?->toISOString(),
                    'category'    => 'sisa_dana',
                    'type'        => 'pemasukan', // sisa yang dikembalikan = uang masuk kembali
                    'description' => 'Sisa Dana Dikembalikan'
                        . ($e->description ? ': ' . $e->description : '')
                        . ($e->fund?->notes ? ' (Dana: ' . $e->fund->notes . ')' : ''),
                    'amount'      => (int) $e->amount,
                ];
            }
        }

        // 4. Info / Pengeluaran Langsung — Bon/Reimburse/Realisasi yang disetujui (DriverOperationalExpense type='expense', approved)
        foreach ($booking->operationalExpenses as $e) {
            if (in_array($e->status, ['approved', 'void_requested'], true) && $e->type === 'expense') {
                if ($e->driver_operational_fund_id === null) {
                    // Realisasi langsung oleh finance (pengeluaran kas langsung)
                    $history[] = [
                        'id'          => 'direct-expense-' . $e->id,
                        'date'        => $e->reviewed_at?->toISOString() ?? $e->created_at?->toISOString(),
                        'category'    => 'operasional',
                        'type'        => 'pengeluaran',
                        'description' => 'Realisasi Langsung: ' . ($e->costType?->nama ?? $e->description),
                        'amount'      => -(int) $e->amount,
                    ];
                } else {
                    // Bon/reimburse dari dana driver (tidak mengurangi kas lagi)
                    $history[] = [
                        'id'          => 'bon-' . $e->id,
                        'date'        => $e->reviewed_at?->toISOString() ?? $e->created_at?->toISOString(),
                        'category'    => 'bon_operasional',
                        'type'        => 'info', // bukan pengeluaran kas tambahan, hanya rincian penggunaan dana
                        'description' => 'Bon/Reimburse: ' . ($e->costType?->nama ?? $e->description),
                        'amount'      => (int) $e->amount,
                    ];
                }
            }
        }

        // 4b. Pengeluaran — Top-up Kartu Fleet ke Driver (FleetCardFund)
        foreach ($booking->fleetCardFunds as $fund) {
            if (in_array($fund->status, ['pending_driver_acceptance', 'accepted', 'closed'], true) && (int) $fund->amount > 0) {
                $history[] = [
                    'id'          => 'fleet-card-fund-' . $fund->id,
                    'date'        => $fund->accepted_at?->toISOString() ?? $fund->created_at?->toISOString(),
                    'category'    => 'kartu_fleet',
                    'type'        => 'pengeluaran',
                    'description' => 'Top-up Kartu Fleet ' . ($fund->fleetCard?->code ?? '')
                        . ($fund->notes ? ': ' . $fund->notes : ''),
                    'amount'      => -(int) $fund->amount,
                ];
            }
        }

        // 4c. Pemasukan kembali — Sisa Kartu Fleet Dikembalikan Driver (FleetCardExpense type='return', approved)
        foreach ($booking->fleetCardExpenses as $e) {
            if (in_array($e->status, ['approved', 'void_requested'], true) && $e->type === 'return') {
                $history[] = [
                    'id'          => 'fleet-card-return-' . $e->id,
                    'date'        => $e->reviewed_at?->toISOString() ?? $e->created_at?->toISOString(),
                    'category'    => 'kartu_fleet',
                    'type'        => 'pemasukan',
                    'description' => 'Sisa Kartu Fleet Dikembalikan' . ($e->description ? ': ' . $e->description : ''),
                    'amount'      => (int) $e->amount,
                ];
            }
        }

        // 4d. Info — Bon Kartu Fleet yang disetujui (FleetCardExpense type='expense', approved)
        foreach ($booking->fleetCardExpenses as $e) {
            if (in_array($e->status, ['approved', 'void_requested'], true) && $e->type === 'expense') {
                $history[] = [
                    'id'          => 'fleet-card-bon-' . $e->id,
                    'date'        => $e->reviewed_at?->toISOString() ?? $e->created_at?->toISOString(),
                    'category'    => 'bon_kartu_fleet',
                    'type'        => 'info',
                    'description' => 'Bon Kartu Fleet: ' . ($e->costType?->nama ?? $e->description),
                    'amount'      => (int) $e->amount,
                ];
            }
        }

        // 5. Pengeluaran — Rent to Rent Debts (Accrued R2R Expense)
        foreach ($booking->rentToRentDebts as $debt) {
            if ($debt->status !== 'cancelled') {
                $history[] = [
                    'id'          => 'r2r-debt-' . $debt->id,
                    'date'        => $debt->created_at?->toISOString(),
                    'category'    => 'rent_to_rent',
                    'type'        => 'pengeluaran',
                    'description' => 'Biaya R2R: ' . ($debt->rentalOwner?->nama ?? 'Owner') 
                        . ($debt->cached_payment_status ? ' (Status: ' . ucfirst($debt->cached_payment_status) . ')' : ''),
                    'amount'      => -(int) $debt->cached_total_amount,
                ];
            }
        }

        // 6. Pengeluaran — Harga Modal Unit (Hanya untuk unit milik DRENT)
        $totalModalUnit = 0;
        foreach ($booking->bookingDetails as $detail) {
            if ($detail->status !== 'batal' && $detail->unit_id && $detail->modal_mobil > 0) {
                // Pastikan bukan unit rent-to-rent
                $isRentToRent = $detail->unit?->rentalOwner && !$detail->unit->rentalOwner->is_owner;
                if (!$isRentToRent) {
                    $nominalModal = $detail->modal_mobil * ($detail->lama_sewa > 0 ? $detail->lama_sewa : 1);
                    $totalModalUnit += $nominalModal;
                    $history[] = [
                        'id'           => 'modal-unit-' . $detail->id,
                        'date'         => $detail->created_at?->toISOString(),
                        'category'     => 'harga_modal',
                        'type'         => 'pengeluaran',
                        'is_unrealized' => true,
                        'description'  => 'Harga Modal Unit: ' . ($detail->unit->tipe ?? 'Unit') . ' (' . $detail->lama_sewa . 'x @ ' . number_format($detail->modal_mobil, 0, ',', '.') . ')',
                        'amount'       => -(int) $nominalModal,
                    ];
                }
            }
        }

        // Sort unified history by date descending
        usort($history, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Compute summaries
        // - total_pemasukan: booking payments + sisa dana dikembalikan driver
        // - total_pengeluaran: dana diserahkan ke driver + bayar R2R
        // - total_bon_info: realisasi bon/reimburse (informasi saja, sudah masuk dalam dana diserahkan)
        $totalPemasukan   = 0;
        $totalPengeluaran = 0;
        $totalBonInfo     = 0;
        foreach ($history as $item) {
            if ($item['type'] === 'pemasukan') {
                $totalPemasukan += $item['amount'];
            } elseif ($item['type'] === 'pengeluaran') {
                $totalPengeluaran += abs($item['amount']);
            } elseif ($item['type'] === 'info') {
                $totalBonInfo += $item['amount'];
            }
        }
        $margin = $totalPemasukan - $totalPengeluaran;

        $detail = $booking->bookingDetails->where('status', '!=', 'batal')->first()
            ?? $booking->bookingDetails->first();

        $unitInfo = null;
        if ($detail) {
            $unit = $detail->unit;
            $rentalOwner = $unit?->rentalOwner;
            $unitInfo = [
                'tipe' => $unit?->tipe ?? $detail->unit_placeholder ?? '-',
                'no_polisi' => $unit?->no_polisi ?? '-',
                'pemilik' => $rentalOwner?->nama ?? 'DRENT',
                'is_rent_to_rent' => $rentalOwner ? !$rentalOwner->is_owner : false,
            ];
        }

        $periodeInfo = null;
        if ($detail) {
            $periodeInfo = [
                'tgl_sewa' => $detail->tgl_sewa,
                'tgl_kembali' => $detail->tgl_kembali,
                'paket' => $booking->lama_sewa . ' x ' . ucfirst($booking->paket_sewa),
                'tujuan' => $booking->tujuan,
            ];
        }

        return response()->json([
            'data' => [
                'id' => $booking->id,
                'kode_booking' => $booking->kode_booking,
                'status' => $booking->status,
                'kota' => $booking->kota,
                'tujuan' => $booking->tujuan,
                'customer' => [
                    'id' => $booking->customer?->id,
                    'nama' => $booking->customer?->nama ?? '-',
                    'status' => $booking->customer?->status ?? 'non-member',
                    'kota' => $booking->customer?->kota ?? '-',
                ],
                'unit' => $unitInfo,
                'periode' => $periodeInfo,
                'history' => $history,
                'summary' => [
                    'total_pemasukan'   => $totalPemasukan,
                    'total_pengeluaran' => $totalPengeluaran,
                    'total_bon_info'    => $totalBonInfo,
                    'total_modal'       => $totalModalUnit,
                    'margin'            => $margin,
                ]
            ]
        ]);
    }

    private function arrayFilter(mixed $value): array
    {
        if (is_null($value) || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter((array) $value, fn($item) => $item !== null && $item !== ''));
    }
}
