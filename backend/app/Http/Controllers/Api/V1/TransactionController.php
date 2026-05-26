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
        // If status filter is empty, default to waiting_list, selesai, batal to match requirements
        if (empty($statuses)) {
            $statuses = ['waiting_list', 'selesai', 'batal'];
        }

        $query = Booking::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            // Total dana R2R yang harus dibayarkan ke pemilik
            ->withSum(['rentToRentDebts as total_rent_to_rent' => fn($q) => $q->where('status', '!=', 'cancelled')], 'cached_total_amount')
            // Total dana yang diserahkan ke driver (gross disbursement)
            ->withSum(['operationalFunds as total_fund_disbursed' => fn($q) => $q->whereIn('status', ['pending_driver_acceptance', 'accepted', 'closed'])], 'amount')
            // Total sisa dana yang dikembalikan driver (approved/void_requested return expenses)
            ->withSum(['operationalExpenses as total_fund_returned' => fn($q) => $q->whereIn('status', ['approved', 'void_requested'])->where('type', 'return')], 'amount')
            // Total bon/reimburse yang disetujui (realisasi pengeluaran - untuk info)
            ->withSum(['operationalExpenses as total_expense_approved' => fn($q) => $q->whereIn('status', ['approved', 'void_requested'])->where('type', 'expense')], 'amount')
            // Total realisasi langsung (approved expense where driver_operational_fund_id IS NULL)
            ->withSum(['operationalExpenses as total_direct_expense' => fn($q) => $q->whereIn('status', ['approved', 'void_requested'])->where('type', 'expense')->whereNull('driver_operational_fund_id')], 'amount')
            ->with([
                'customer:id,nama,status',
                'bookingDetails.unit.rentalOwner:id,nama,is_owner',
                'bookingDetails.costs.costType',
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

        $query->orderBy('created_at', 'desc');

        $perPage = $request->integer('per_page', 15);
        $bookings = $query->paginate($perPage);

        return TransactionListResource::collection($bookings);
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

        $booking->load([
            'customer:id,nama,status,kota',
            'bookingDetails.unit.rentalOwner',
            'bookingDetails.costs.costType',
            'payments.paymentAccount',
            'payments.creator',
            'operationalFunds.paymentAccount',
            'operationalExpenses.costType',
            'operationalExpenses.fund',
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
                $history[] = [
                    'id'          => 'fund-' . $fund->id,
                    'date'        => $fund->accepted_at?->toISOString() ?? $fund->created_at?->toISOString(),
                    'category'    => 'dana_operasional',
                    'type'        => 'pengeluaran',
                    'description' => 'Dana Operasional → Driver'
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

        // 5. Pengeluaran — Rent to Rent Payments (allocations linked to booking debts)
        $allocations = RentToRentPaymentAllocation::query()
            ->whereHas('debt', fn($q) => $q->where('booking_id', $booking->id))
            ->with(['payment.paymentAccount', 'debt.rentalOwner'])
            ->get();

        foreach ($allocations as $allocation) {
            $payment = $allocation->payment;
            if ($payment && ($payment->status ?? 'active') !== 'voided') {
                $history[] = [
                    'id'          => 'r2r-' . $allocation->id,
                    'date'        => $payment->paid_at?->toISOString() ?? $payment->created_at?->toISOString(),
                    'category'    => 'rent_to_rent',
                    'type'        => 'pengeluaran',
                    'description' => 'Bayar Owner R2R: ' . ($allocation->debt->rentalOwner?->nama ?? 'Owner')
                        . ($payment->paymentAccount ? ' (' . $payment->paymentAccount->nama_bank . ')' : ''),
                    'amount'      => -(int) $allocation->amount,
                ];
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
