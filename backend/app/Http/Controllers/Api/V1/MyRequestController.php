<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DriverOperationalExpense;
use App\Models\RentToRentAmountChangeRequest;
use App\Models\RentToRentBill;
use App\Models\RentToRentPayment;
use Illuminate\Http\Request;

/**
 * Returns the change requests created by the currently logged-in user
 * across all request types and all statuses (pending / approved / rejected).
 *
 * This is the "user side" counterpart of {@see SupervisorRequestController}
 * which lists pending requests addressed to a supervisor's inbox.
 */
class MyRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        $voidPayments = BookingPayment::query()
            ->with(['booking.customer', 'paymentAccount', 'voidApprover', 'voidRejecter'])
            ->where('void_requested_by', $userId)
            ->whereNotNull('void_requested_at')
            ->orderByDesc('void_requested_at')
            ->get()
            ->map(function (BookingPayment $payment) {
                $status = $this->resolveVoidStatus($payment->void_approved_at, $payment->void_rejected_at);

                return [
                    'id' => 'void_payment_'.$payment->id,
                    'type' => 'void_payment',
                    'type_label' => 'Void Pembayaran',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $payment->void_requested_at?->toISOString(),
                    'reviewed_at' => $payment->void_approved_at?->toISOString()
                        ?? $payment->void_rejected_at?->toISOString(),
                    'reason' => $payment->void_reason,
                    'rejection_note' => $payment->void_rejection_note,
                    'booking' => [
                        'id' => $payment->booking?->id,
                        'kode_booking' => $payment->booking?->kode_booking,
                        'customer_name' => $payment->booking?->customer?->nama,
                    ],
                    'detail_primary' => sprintf(
                        '%s - %s',
                        $this->formatCurrency($payment->amount),
                        $payment->payment_type ?? '-'
                    ),
                    'detail_secondary' => $payment->paymentAccount
                        ? trim(($payment->paymentAccount->nama_bank ?? '-').' '.($payment->paymentAccount->nomor_rekening ?? ''))
                        : '-',
                    'reviewer' => $this->resolveReviewer($status, $payment->voidApprover, $payment->voidRejecter),
                ];
            });

        $returnRequests = Booking::query()
            ->with(['customer', 'rentalUnitReturnApprover', 'rentalUnitReturnRejecter'])
            ->where('rental_unit_return_requested_by', $userId)
            ->whereNotNull('rental_unit_return_requested_at')
            ->orderByDesc('rental_unit_return_requested_at')
            ->get()
            ->map(function (Booking $booking) {
                $status = $booking->rental_unit_return_status ?: 'pending';

                return [
                    'id' => 'return_rental_unit_'.$booking->id,
                    'type' => 'return_rental_unit',
                    'type_label' => 'Kembali ke Rental Unit',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $booking->rental_unit_return_requested_at?->toISOString(),
                    'reviewed_at' => $booking->rental_unit_return_approved_at?->toISOString()
                        ?? $booking->rental_unit_return_rejected_at?->toISOString(),
                    'reason' => $booking->rental_unit_return_reason,
                    'rejection_note' => $booking->rental_unit_return_rejection_note,
                    'booking' => [
                        'id' => $booking->id,
                        'kode_booking' => $booking->kode_booking,
                        'customer_name' => $booking->customer?->nama,
                    ],
                    'detail_primary' => 'Ubah status booking dari selesai ke rental_unit',
                    'detail_secondary' => $booking->kode_booking ?? '-',
                    'reviewer' => $this->resolveReviewer(
                        $status,
                        $booking->rentalUnitReturnApprover,
                        $booking->rentalUnitReturnRejecter
                    ),
                ];
            });

        $revertRequests = Booking::query()
            ->with(['customer', 'operationalRevertApprover', 'operationalRevertRejecter'])
            ->where('operational_revert_requested_by', $userId)
            ->whereNotNull('operational_revert_requested_at')
            ->orderByDesc('operational_revert_requested_at')
            ->get()
            ->map(function (Booking $booking) {
                $status = $booking->operational_revert_status ?: 'pending';

                return [
                    'id' => 'operational_revert_'.$booking->id,
                    'type' => 'operational_revert',
                    'type_label' => 'Revert Operasional Aktif',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $booking->operational_revert_requested_at?->toISOString(),
                    'reviewed_at' => $booking->operational_revert_approved_at?->toISOString()
                        ?? $booking->operational_revert_rejected_at?->toISOString(),
                    'reason' => $booking->operational_revert_reason,
                    'rejection_note' => $booking->operational_revert_rejection_note,
                    'booking' => [
                        'id' => $booking->id,
                        'kode_booking' => $booking->kode_booking,
                        'customer_name' => $booking->customer?->nama,
                    ],
                    'detail_primary' => 'Aktifkan kembali operasional (kembali ke operasional aktif)',
                    'detail_secondary' => $booking->kode_booking ?? '-',
                    'reviewer' => $this->resolveReviewer(
                        $status,
                        $booking->operationalRevertApprover,
                        $booking->operationalRevertRejecter
                    ),
                ];
            });

        $r2rVoidBills = RentToRentBill::query()
            ->with(['rentalOwner', 'items.debt.booking', 'voidApprover', 'voidRejecter'])
            ->where('void_requested_by', $userId)
            ->whereNotNull('void_requested_at')
            ->orderByDesc('void_requested_at')
            ->get()
            ->map(function (RentToRentBill $bill) {
                $status = $this->resolveVoidStatus($bill->void_approved_at, $bill->void_rejected_at);
                $bookingCodes = $bill->items->pluck('debt.booking.kode_booking')->filter()->unique()->values();

                return [
                    'id' => 'rent_to_rent_void_bill_'.$bill->id,
                    'type' => 'rent_to_rent_void_bill',
                    'type_label' => 'Void Tagihan Rent to Rent',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $bill->void_requested_at?->toISOString(),
                    'reviewed_at' => $bill->void_approved_at?->toISOString()
                        ?? $bill->void_rejected_at?->toISOString(),
                    'reason' => $bill->void_reason,
                    'rejection_note' => $bill->void_rejection_note,
                    'booking' => [
                        'id' => null,
                        'kode_booking' => $bill->bill_number,
                        'customer_name' => $bill->rentalOwner?->nama,
                    ],
                    'detail_primary' => sprintf(
                        '%s - sudah bayar %s',
                        $this->formatCurrency($bill->total_amount),
                        $this->formatCurrency($bill->paid_amount)
                    ),
                    'detail_secondary' => $bookingCodes->join(', ') ?: '-',
                    'reviewer' => $this->resolveReviewer($status, $bill->voidApprover, $bill->voidRejecter),
                ];
            });

        $r2rVoidPayments = RentToRentPayment::query()
            ->with([
                'bill.rentalOwner',
                'bill.items.debt.booking',
                'allocations.debt.rentalOwner',
                'allocations.debt.booking',
                'paymentAccount',
                'voidApprover',
                'voidRejecter',
            ])
            ->where('void_requested_by', $userId)
            ->whereNotNull('void_requested_at')
            ->orderByDesc('void_requested_at')
            ->get()
            ->map(function (RentToRentPayment $payment) {
                $status = $this->resolveVoidStatus($payment->void_approved_at, $payment->void_rejected_at);
                $directDebts = $payment->allocations->pluck('debt')->filter();
                $bookingCodes = $payment->bill?->items?->pluck('debt.booking.kode_booking')->filter()->unique()->values()
                    ?? $directDebts->pluck('booking.kode_booking')->filter()->unique()->values();
                $ownerName = $payment->bill?->rentalOwner?->nama
                    ?? $directDebts->pluck('rentalOwner.nama')->filter()->unique()->join(', ');

                return [
                    'id' => 'rent_to_rent_void_payment_'.$payment->id,
                    'type' => 'rent_to_rent_void_payment',
                    'type_label' => 'Void Pembayaran Rent to Rent',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $payment->void_requested_at?->toISOString(),
                    'reviewed_at' => $payment->void_approved_at?->toISOString()
                        ?? $payment->void_rejected_at?->toISOString(),
                    'reason' => $payment->void_reason,
                    'rejection_note' => $payment->void_rejection_note,
                    'booking' => [
                        'id' => null,
                        'kode_booking' => $payment->bill?->bill_number ?: ($bookingCodes->join(', ') ?: '-'),
                        'customer_name' => $ownerName ?: '-',
                    ],
                    'detail_primary' => sprintf(
                        '%s - pembayaran rent-to-rent',
                        $this->formatCurrency($payment->amount)
                    ),
                    'detail_secondary' => $payment->paymentAccount
                        ? trim(($payment->paymentAccount->nama_bank ?? '-').' '.($payment->paymentAccount->nomor_rekening ?? ''))
                        : '-',
                    'reviewer' => $this->resolveReviewer($status, $payment->voidApprover, $payment->voidRejecter),
                ];
            });

        $voidExpenses = DriverOperationalExpense::query()
            ->with(['booking.customer', 'voidApprover', 'voidRejecter'])
            ->where('void_requested_by', $userId)
            ->whereNotNull('void_requested_at')
            ->orderByDesc('void_requested_at')
            ->get()
            ->map(function (DriverOperationalExpense $expense) {
                // void approved => status becomes 'rejected' with [VOID] prefix in rejection_reason
                if ($expense->void_approved_at) {
                    $status = 'approved';
                } elseif ($expense->void_rejected_at) {
                    $status = 'rejected';
                } else {
                    $status = 'pending';
                }

                return [
                    'id' => 'void_expense_'.$expense->id,
                    'type' => 'void_operational_expense',
                    'type_label' => 'Void Bon / Realisasi',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $expense->void_requested_at?->toISOString(),
                    'reviewed_at' => $expense->void_approved_at?->toISOString()
                        ?? $expense->void_rejected_at?->toISOString(),
                    'reason' => $expense->void_reason,
                    'rejection_note' => $expense->void_rejection_note,
                    'booking' => [
                        'id' => $expense->booking?->id,
                        'kode_booking' => $expense->booking?->kode_booking,
                        'customer_name' => $expense->booking?->customer?->nama,
                    ],
                    'detail_primary' => sprintf(
                        'Void Bon: %s - %s',
                        $this->formatCurrency($expense->amount),
                        $expense->type === 'return' ? 'Kembalikan Sisa Dana' : 'Pembayaran Bon'
                    ),
                    'detail_secondary' => $expense->booking?->kode_booking ?? '-',
                    'reviewer' => $this->resolveReviewer($status, $expense->voidApprover, $expense->voidRejecter),
                ];
            });

        $amountChanges = RentToRentAmountChangeRequest::query()
            ->with(['debt.booking.customer', 'debt.rentalOwner', 'approvedBy', 'rejectedBy'])
            ->where('requested_by', $userId)
            ->orderByDesc('requested_at')
            ->get()
            ->map(function (RentToRentAmountChangeRequest $req) {
                $status = $req->status ?: 'pending';
                $reviewer = match ($status) {
                    'approved' => $req->approvedBy,
                    'rejected' => $req->rejectedBy,
                    default => null,
                };

                return [
                    'id' => 'r2r_amount_change_'.$req->id,
                    'type' => 'rent_to_rent_amount_change',
                    'type_label' => 'Ubah Nominal Rent to Rent',
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'requested_at' => $req->requested_at?->toISOString(),
                    'reviewed_at' => $req->reviewed_at?->toISOString(),
                    'reason' => $req->reason,
                    'rejection_note' => $req->rejection_note,
                    'booking' => [
                        'id' => $req->debt?->booking?->id,
                        'kode_booking' => $req->debt?->booking?->kode_booking,
                        'customer_name' => $req->debt?->rentalOwner?->nama,
                    ],
                    'detail_primary' => sprintf(
                        'Ubah Nominal -> %s',
                        $req->requested_amount_override !== null
                            ? $this->formatCurrency($req->requested_amount_override)
                            : 'Reset ke Default (Modal Booking)'
                    ),
                    'detail_secondary' => 'Hutang R2R: '.($req->debt?->booking?->kode_booking ?? '-'),
                    'reviewer' => $reviewer ? [
                        'id' => $reviewer->id,
                        'name' => $reviewer->name,
                    ] : null,
                ];
            });

        $requests = $voidPayments
            ->concat($returnRequests)
            ->concat($revertRequests)
            ->concat($r2rVoidBills)
            ->concat($r2rVoidPayments)
            ->concat($voidExpenses)
            ->concat($amountChanges)
            ->sortByDesc(fn ($item) => $item['requested_at'] ?? '')
            ->values();

        return response()->json(['data' => $requests]);
    }

    private function resolveVoidStatus(?\DateTimeInterface $approvedAt, ?\DateTimeInterface $rejectedAt): string
    {
        if ($approvedAt) {
            return 'approved';
        }
        if ($rejectedAt) {
            return 'rejected';
        }
        return 'pending';
    }

    private function resolveReviewer(string $status, $approver, $rejecter): ?array
    {
        $user = match ($status) {
            'approved' => $approver,
            'rejected' => $rejecter,
            default => null,
        };

        if (!$user) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu ACC',
        };
    }

    private function formatCurrency($amount): string
    {
        return 'Rp '.number_format((int) $amount, 0, ',', '.');
    }
}
