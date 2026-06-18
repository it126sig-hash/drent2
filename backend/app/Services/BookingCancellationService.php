<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BookingCancellationService
{
    public function __construct(private PaymentAccountTransactionService $transactionService)
    {
    }

    public function getAll(array $filters = [])
    {
        $user = Auth::user();

        return BookingCancellation::query()
            ->with(['booking.customer', 'paymentAccount', 'creator', 'dibayarOleh'])
            ->where('tenant_id', $user->tenant_id)
            ->when($user->role !== 'superadmin', fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when(isset($filters['branch_id']), fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(isset($filters['ada_refund']), fn ($q) => $q->where('ada_refund', (bool) $filters['ada_refund']))
            ->when(isset($filters['sudah_bayar_refund']), fn ($q) => $q->where('sudah_bayar_refund', (bool) $filters['sudah_bayar_refund']))
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(Booking $booking, array $data): BookingCancellation
    {
        return BookingCancellation::create([
            'tenant_id'       => $booking->tenant_id,
            'branch_id'       => $booking->branch_id,
            'booking_id'      => $booking->id,
            'ada_refund'      => (bool) ($data['ada_refund'] ?? false),
            'nominal_refund'  => ($data['ada_refund'] ?? false) ? ($data['nominal_refund'] ?? null) : null,
            'bank_refund'     => ($data['ada_refund'] ?? false) ? ($data['bank_refund'] ?? null) : null,
            'no_rek_refund'   => ($data['ada_refund'] ?? false) ? ($data['no_rek_refund'] ?? null) : null,
            'nama_rek_refund' => ($data['ada_refund'] ?? false) ? ($data['nama_rek_refund'] ?? null) : null,
            'catatan_refund'  => $data['catatan_refund'] ?? null,
            'created_by'      => Auth::id(),
        ]);
    }

    public function update(BookingCancellation $cancellation, array $data): BookingCancellation
    {
        if ($cancellation->sudah_bayar_refund) {
            throw new UnprocessableEntityHttpException('Refund sudah dibayar dan tidak dapat diubah.');
        }

        $adaRefund = (bool) ($data['ada_refund'] ?? $cancellation->ada_refund);

        $cancellation->update([
            'ada_refund'      => $adaRefund,
            'nominal_refund'  => $adaRefund ? ($data['nominal_refund'] ?? $cancellation->nominal_refund) : null,
            'bank_refund'     => $adaRefund ? ($data['bank_refund'] ?? $cancellation->bank_refund) : null,
            'no_rek_refund'   => $adaRefund ? ($data['no_rek_refund'] ?? $cancellation->no_rek_refund) : null,
            'nama_rek_refund' => $adaRefund ? ($data['nama_rek_refund'] ?? $cancellation->nama_rek_refund) : null,
            'catatan_refund'  => $data['catatan_refund'] ?? $cancellation->catatan_refund,
        ]);

        return $cancellation->fresh(['booking.customer', 'paymentAccount', 'creator', 'dibayarOleh']);
    }

    public function payRefund(BookingCancellation $cancellation, array $data): BookingCancellation
    {
        if (!$cancellation->ada_refund) {
            throw new UnprocessableEntityHttpException('Pembatalan ini tidak memiliki refund.');
        }

        if ($cancellation->sudah_bayar_refund) {
            throw new UnprocessableEntityHttpException('Refund sudah pernah dibayar.');
        }

        $nominalAktual = isset($data['nominal_dibayar']) && $data['nominal_dibayar'] > 0
            ? (int) $data['nominal_dibayar']
            : (int) $cancellation->nominal_refund;

        if (!$nominalAktual || $nominalAktual <= 0) {
            throw new UnprocessableEntityHttpException('Nominal refund tidak valid.');
        }

        $paymentAccountId = (int) $data['payment_account_id'];
        $catatan = $data['catatan'] ?? null;

        return DB::transaction(function () use ($cancellation, $paymentAccountId, $nominalAktual, $catatan) {
            $account = PaymentAccount::lockForUpdate()->findOrFail($paymentAccountId);

            $description = 'Refund pembatalan booking #' . $cancellation->booking->kode_booking;
            if ($catatan) {
                $description .= ' | ' . $catatan;
            }

            $this->transactionService->applyDelta($account, -$nominalAktual, [
                'type'           => 'refund_out',
                'amount'         => $nominalAktual,
                'description'    => $description,
                'created_by'     => Auth::id(),
                'transaction_at' => now(),
            ]);

            $cancellation->update([
                'sudah_bayar_refund' => true,
                'nominal_refund'     => $nominalAktual,
                'payment_account_id' => $paymentAccountId,
                'dibayar_at'         => now(),
                'dibayar_oleh'       => Auth::id(),
            ]);

            return $cancellation->fresh(['booking.customer', 'paymentAccount', 'creator', 'dibayarOleh']);
        });
    }
}
