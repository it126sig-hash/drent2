<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\RentToRentBill;
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

        $requests = $voidPayments
            ->concat($returnRequests)
            ->concat($rentToRentVoidBills)
            ->sortByDesc(fn($item) => $item['approved_at'] ?? $item['requested_at'] ?? '')
            ->values();

        return response()->json(['data' => $requests]);
    }
}
