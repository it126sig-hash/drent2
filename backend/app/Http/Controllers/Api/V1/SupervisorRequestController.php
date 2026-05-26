<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\RentToRentBill;
use App\Models\RentToRentPayment;
use Illuminate\Http\Request;

class SupervisorRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, ['superadmin', 'supervisor']), 403);

        $voidPayments = BookingPayment::query()
            ->with(['booking.customer', 'paymentAccount', 'creator', 'voidRequester', 'voidApprover'])
            ->whereIn('status', ['void_requested', 'voided'])
            ->when($user->role !== 'superadmin', fn($q) => $q->whereHas(
                'booking',
                fn($booking) => $booking->where('branch_id', $user->branch_id)
            ))
            ->latest('updated_at')
            ->get()
            ->map(fn($payment) => [
                'id' => 'void_payment_'.$payment->id,
                'type' => 'void_payment',
                'type_label' => 'Void Pembayaran',
                'status' => $payment->status === 'voided' ? 'approved' : 'pending',
                'status_label' => $payment->status === 'voided' ? 'Disetujui' : 'Menunggu ACC',
                'requested_at' => $payment->void_requested_at?->toISOString(),
                'approved_at' => $payment->void_approved_at?->toISOString(),
                'reason' => $payment->void_reason,
                'booking' => [
                    'id' => $payment->booking?->id,
                    'kode_booking' => $payment->booking?->kode_booking,
                    'customer_name' => $payment->booking?->customer?->nama,
                ],
                'payment' => [
                    'id' => $payment->id,
                    'amount' => (int) $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'payment_account' => $payment->paymentAccount ? [
                        'nama_bank' => $payment->paymentAccount->nama_bank,
                        'nomor_rekening' => $payment->paymentAccount->nomor_rekening,
                    ] : null,
                ],
                'requester' => $payment->voidRequester ? [
                    'id' => $payment->voidRequester->id,
                    'name' => $payment->voidRequester->name,
                ] : null,
                'approver' => $payment->voidApprover ? [
                    'id' => $payment->voidApprover->id,
                    'name' => $payment->voidApprover->name,
                ] : null,
            ]);

        $returnRequests = Booking::query()
            ->with(['customer', 'rentalUnitReturnRequester', 'rentalUnitReturnApprover'])
            ->whereIn('rental_unit_return_status', ['pending', 'approved'])
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            ->latest('updated_at')
            ->get()
            ->map(fn($booking) => [
                'id' => 'return_rental_unit_'.$booking->id,
                'type' => 'return_rental_unit',
                'type_label' => 'Kembali ke Rental Unit',
                'status' => $booking->rental_unit_return_status,
                'status_label' => $booking->rental_unit_return_status === 'approved' ? 'Disetujui' : 'Menunggu ACC',
                'requested_at' => $booking->rental_unit_return_requested_at?->toISOString(),
                'approved_at' => $booking->rental_unit_return_approved_at?->toISOString(),
                'reason' => $booking->rental_unit_return_reason,
                'booking' => [
                    'id' => $booking->id,
                    'kode_booking' => $booking->kode_booking,
                    'customer_name' => $booking->customer?->nama,
                ],
                'payment' => null,
                'requester' => $booking->rentalUnitReturnRequester ? [
                    'id' => $booking->rentalUnitReturnRequester->id,
                    'name' => $booking->rentalUnitReturnRequester->name,
                ] : null,
                'approver' => $booking->rentalUnitReturnApprover ? [
                    'id' => $booking->rentalUnitReturnApprover->id,
                    'name' => $booking->rentalUnitReturnApprover->name,
                ] : null,
            ]);

        $revertRequests = Booking::query()
            ->with(['customer', 'operationalRevertRequester', 'operationalRevertApprover'])
            ->whereIn('operational_revert_status', ['pending', 'approved'])
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            ->latest('updated_at')
            ->get()
            ->map(fn($booking) => [
                'id' => 'operational_revert_'.$booking->id,
                'type' => 'operational_revert',
                'type_label' => 'Revert Operasional Aktif',
                'status' => $booking->operational_revert_status === 'approved' ? 'approved' : 'pending',
                'status_label' => $booking->operational_revert_status === 'approved' ? 'Disetujui' : 'Menunggu ACC',
                'requested_at' => $booking->operational_revert_requested_at?->toISOString(),
                'approved_at' => $booking->operational_revert_approved_at?->toISOString(),
                'reason' => $booking->operational_revert_reason,
                'booking' => [
                    'id' => $booking->id,
                    'kode_booking' => $booking->kode_booking,
                    'customer_name' => $booking->customer?->nama,
                ],
                'payment' => null,
                'requester' => $booking->operationalRevertRequester ? [
                    'id' => $booking->operationalRevertRequester->id,
                    'name' => $booking->operationalRevertRequester->name,
                ] : null,
                'approver' => $booking->operationalRevertApprover ? [
                    'id' => $booking->operationalRevertApprover->id,
                    'name' => $booking->operationalRevertApprover->name,
                ] : null,
            ]);

        $rentToRentVoidBills = RentToRentBill::query()
            ->with(['rentalOwner', 'items.debt.booking.customer', 'voidRequester', 'voidApprover'])
            ->whereIn('status', ['void_requested', 'void'])
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            ->latest('updated_at')
            ->get()
            ->map(function (RentToRentBill $bill) {
                $bookingCodes = $bill->items->pluck('debt.booking.kode_booking')->filter()->unique()->values();

                return [
                    'id' => 'rent_to_rent_void_bill_'.$bill->id,
                    'type' => 'rent_to_rent_void_bill',
                    'type_label' => 'Void Tagihan Rent to Rent',
                    'status' => $bill->status === 'void' ? 'approved' : 'pending',
                    'status_label' => $bill->status === 'void' ? 'Disetujui' : 'Menunggu ACC',
                    'requested_at' => $bill->void_requested_at?->toISOString(),
                    'approved_at' => $bill->void_approved_at?->toISOString(),
                    'reason' => $bill->void_reason,
                    'booking' => [
                        'id' => null,
                        'kode_booking' => $bookingCodes->join(', '),
                        'customer_name' => $bill->rentalOwner?->nama,
                    ],
                    'bill' => [
                        'id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'owner_name' => $bill->rentalOwner?->nama,
                        'total_amount' => (int) $bill->total_amount,
                        'paid_amount' => (int) $bill->paid_amount,
                        'booking_codes' => $bookingCodes,
                    ],
                    'payment' => null,
                    'requester' => $bill->voidRequester ? [
                        'id' => $bill->voidRequester->id,
                        'name' => $bill->voidRequester->name,
                    ] : null,
                    'approver' => $bill->voidApprover ? [
                        'id' => $bill->voidApprover->id,
                        'name' => $bill->voidApprover->name,
                    ] : null,
                ];
            });

        $rentToRentVoidPayments = RentToRentPayment::query()
            ->with(['bill.rentalOwner', 'bill.items.debt.booking.customer', 'allocations.debt.rentalOwner', 'allocations.debt.booking.customer', 'paymentAccount', 'creator', 'voidRequester', 'voidApprover'])
            ->whereIn('status', ['void_requested', 'voided'])
            ->when($user->role !== 'superadmin', function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->whereHas('bill', fn($bill) => $bill->where('branch_id', $user->branch_id))
                        ->orWhereHas('allocations.debt', fn($debt) => $debt->where('branch_id', $user->branch_id));
                });
            })
            ->latest('updated_at')
            ->get()
            ->map(function (RentToRentPayment $payment) {
                $directDebts = $payment->allocations->pluck('debt')->filter();
                $bookingCodes = $payment->bill?->items?->pluck('debt.booking.kode_booking')->filter()->unique()->values()
                    ?? $directDebts->pluck('booking.kode_booking')->filter()->unique()->values();
                $ownerName = $payment->bill?->rentalOwner?->nama
                    ?? $directDebts->pluck('rentalOwner.nama')->filter()->unique()->join(', ');

                return [
                    'id' => 'rent_to_rent_void_payment_'.$payment->id,
                    'type' => 'rent_to_rent_void_payment',
                    'type_label' => 'Void Pembayaran Rent to Rent',
                    'status' => $payment->status === 'voided' ? 'approved' : 'pending',
                    'status_label' => $payment->status === 'voided' ? 'Disetujui' : 'Menunggu ACC',
                    'requested_at' => $payment->void_requested_at?->toISOString(),
                    'approved_at' => $payment->void_approved_at?->toISOString(),
                    'reason' => $payment->void_reason,
                    'booking' => [
                        'id' => null,
                        'kode_booking' => $bookingCodes->join(', '),
                        'customer_name' => $ownerName,
                    ],
                    'bill' => $payment->bill ? [
                        'id' => $payment->bill->id,
                        'bill_number' => $payment->bill->bill_number,
                        'owner_name' => $payment->bill->rentalOwner?->nama,
                        'total_amount' => (int) $payment->bill->total_amount,
                        'paid_amount' => (int) $payment->bill->paid_amount,
                        'booking_codes' => $bookingCodes,
                    ] : null,
                    'payment' => [
                        'id' => $payment->id,
                        'amount' => (int) $payment->amount,
                        'payment_type' => 'rent_to_rent',
                        'paid_at' => $payment->paid_at?->toISOString(),
                        'payment_account' => $payment->paymentAccount ? [
                            'nama_bank' => $payment->paymentAccount->nama_bank,
                            'nomor_rekening' => $payment->paymentAccount->nomor_rekening,
                        ] : null,
                    ],
                    'requester' => $payment->voidRequester ? [
                        'id' => $payment->voidRequester->id,
                        'name' => $payment->voidRequester->name,
                    ] : null,
                    'approver' => $payment->voidApprover ? [
                        'id' => $payment->voidApprover->id,
                        'name' => $payment->voidApprover->name,
                    ] : null,
                ];
            });

        $voidExpenses = \App\Models\DriverOperationalExpense::query()
            ->with(['booking.customer', 'driver', 'submitter', 'reviewer', 'voidRequester', 'voidApprover'])
            ->where(function ($query) {
                $query->where('status', 'void_requested')
                    ->orWhere(function ($q) {
                        $q->where('status', 'rejected')
                          ->where('rejection_reason', 'like', '[VOID]%');
                    });
            })
            ->when($user->role !== 'superadmin', fn($q) => $q->where('branch_id', $user->branch_id))
            ->latest('updated_at')
            ->get()
            ->map(fn($expense) => [
                'id' => 'void_expense_'.$expense->id,
                'type' => 'void_operational_expense',
                'type_label' => 'Void Bon / Realisasi',
                'status' => $expense->status === 'void_requested' ? 'pending' : 'approved',
                'status_label' => $expense->status === 'void_requested' ? 'Menunggu ACC' : 'Disetujui',
                'requested_at' => $expense->void_requested_at?->toISOString(),
                'approved_at' => $expense->void_approved_at?->toISOString(),
                'reason' => $expense->void_reason,
                'booking' => [
                    'id' => $expense->booking?->id,
                    'kode_booking' => $expense->booking?->kode_booking,
                    'customer_name' => $expense->booking?->customer?->nama,
                ],
                'payment' => [
                    'id' => $expense->id,
                    'amount' => (int) $expense->amount,
                    'payment_type' => $expense->type === 'return' ? 'Kembalikan Sisa Dana' : 'Pembayaran Bon',
                    'paid_at' => $expense->created_at?->toISOString(),
                    'payment_account' => null,
                ],
                'requester' => $expense->voidRequester ? [
                    'id' => $expense->voidRequester->id,
                    'name' => $expense->voidRequester->name,
                ] : null,
                'approver' => $expense->voidApprover ? [
                    'id' => $expense->voidApprover->id,
                    'name' => $expense->voidApprover->name,
                ] : null,
            ]);

        $rentToRentAmountChanges = \App\Models\RentToRentAmountChangeRequest::query()
            ->with(['debt.booking.customer', 'debt.rentalOwner', 'debt.bookingDetail.unit', 'requestedBy', 'approvedBy', 'rejectedBy'])
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->when($user->role !== 'superadmin', fn($q) => $q->whereHas(
                'debt',
                fn($debt) => $debt->where('branch_id', $user->branch_id)
            ))
            ->latest('updated_at')
            ->get()
            ->map(fn($req) => [
                'id' => 'r2r_amount_change_'.$req->id,
                'type' => 'rent_to_rent_amount_change',
                'type_label' => 'Ubah Nominal Rent to Rent',
                'status' => $req->status,
                'status_label' => match($req->status) {
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu ACC',
                },
                'requested_at' => $req->requested_at?->toISOString(),
                'approved_at' => $req->reviewed_at?->toISOString(),
                'reason' => $req->reason,
                'booking' => [
                    'id' => $req->debt?->booking?->id,
                    'kode_booking' => $req->debt?->booking?->kode_booking,
                    'customer_name' => $req->debt?->rentalOwner?->nama,
                ],
                'payment' => null,
                'amount_change' => [
                    'id' => $req->id,
                ],
                'debt' => [
                    'id' => $req->debt?->id,
                    'kode_booking' => $req->debt?->booking?->kode_booking,
                    'current_amount' => $req->debt ? app(\App\Services\RentToRentService::class)->currentAmount($req->debt) : 0,
                    'requested_amount' => $req->requested_amount_override,
                ],
                'requester' => $req->requestedBy ? [
                    'id' => $req->requestedBy->id,
                    'name' => $req->requestedBy->name,
                ] : null,
                'approver' => $req->status === 'approved' && $req->approvedBy ? [
                    'id' => $req->approvedBy->id,
                    'name' => $req->approvedBy->name,
                ] : ($req->status === 'rejected' && $req->rejectedBy ? [
                    'id' => $req->rejectedBy->id,
                    'name' => $req->rejectedBy->name,
                ] : null),
            ]);

        $requests = $voidPayments
            ->concat($returnRequests)
            ->concat($revertRequests)
            ->concat($rentToRentVoidBills)
            ->concat($rentToRentVoidPayments)
            ->concat($voidExpenses)
            ->concat($rentToRentAmountChanges)
            ->sortByDesc(fn($item) => $item['approved_at'] ?? $item['requested_at'] ?? '')
            ->values();

        return response()->json(['data' => $requests]);
    }
}
