<?php

namespace App\Http\Resources;

use App\Models\BookingDetail;
use App\Services\BookingBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionListResource extends JsonResource
{
    private ?BookingDetail $rowDetail = null;
    private $allDetails = null;

    /**
     * Render this resource for a single booking_detail row (initial/extend/rolling)
     * instead of the whole booking, so each modification shows as its own row.
     */
    public function forDetail(BookingDetail $detail, $allDetails): static
    {
        $this->rowDetail = $detail;
        $this->allDetails = $allDetails;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->rowDetail;
        $allDetails = $this->allDetails;

        $unit = $detail->unit;
        $rentalOwner = $unit?->rentalOwner;
        $isRentToRent = $rentalOwner ? !$rentalOwner->is_owner : false;

        $unitInfo = [
            'tipe' => $unit?->tipe ?? $detail->unit_placeholder ?? '-',
            'no_polisi' => $unit?->no_polisi ?? '-',
            'pemilik' => $rentalOwner?->nama ?? 'DRENT',
            'is_rent_to_rent' => $isRentToRent,
        ];

        $periodeInfo = [
            'tgl_sewa' => $detail->tgl_sewa,
            'tgl_kembali' => $detail->tgl_kembali,
            'paket' => $detail->lama_sewa . ' x ' . ucfirst($detail->paket_sewa),
            'tujuan' => $this->tujuan,
        ];

        $initialDetail = $allDetails->firstWhere('detail_type', 'initial') ?? $allDetails->first();
        $isLastDetail = $allDetails->last()?->is($detail) ?? false;

        if ($this->status === 'batal') {
            // Cancel totals (uang masuk / refund) are booking-wide, not per-detail —
            // show them once on the most recent row only, zero on the rest.
            $totalBiaya         = $isLastDetail ? (int) ($this->total_payments_in ?? 0) : 0;
            $totalRentToRent    = 0;
            $totalOperasional   = 0;
            $totalFundDisbursed = 0;
            $totalFundReturned  = 0;
            $totalBonApproved   = 0;
            $totalDirectExpense = 0;
            $totalCardTopup     = 0;
            $totalCardReturn    = 0;
            $totalCardBon       = 0;
            $totalModal         = 0;
            $totalPengeluaran   = $isLastDetail ? (int) ($this->cancellation?->nominal_refund ?? 0) : 0;
        } else {
            $totalBiaya = $this->computeDetailRevenue($detail);

            $totalRentToRent = (int) $this->rentToRentDebts
                ->where('booking_detail_id', $detail->id)
                ->sum('cached_total_amount');

            $detailFunds    = $this->operationalFunds->where('booking_detail_id', $detail->id);
            $detailExpenses = $this->operationalExpenses->where('booking_detail_id', $detail->id);

            $totalFundDisbursed = (int) $detailFunds->sum('amount');
            $totalFundReturned  = (int) $detailExpenses->where('type', 'return')->sum('amount');
            $totalBonApproved   = (int) $detailExpenses->where('type', 'expense')->sum('amount');
            $totalDirectExpense = (int) $detailExpenses->where('type', 'expense')->whereNull('driver_operational_fund_id')->sum('amount');
            // Net operasional = dana yang diserahkan ke driver - sisa yang dikembalikan + pengeluaran langsung
            $totalOperasional = max(0, $totalFundDisbursed - $totalFundReturned) + $totalDirectExpense;

            // Kartu Fleet — kolom terpisah dari deposit operasional, supaya cash vs kartu tetap bisa dibedakan per periode.
            $detailCardFunds    = $this->fleetCardFunds->where('booking_detail_id', $detail->id);
            $detailCardExpenses = $this->fleetCardExpenses->where('booking_detail_id', $detail->id);

            $totalCardTopup  = (int) $detailCardFunds->sum('amount');
            $totalCardReturn = (int) $detailCardExpenses->where('type', 'return')->sum('amount');
            $totalCardBon    = (int) $detailCardExpenses->where('type', 'expense')->sum('amount');
            $totalCardNet    = max(0, $totalCardTopup - $totalCardReturn);

            // Modal unit internal (bukan R2R) — belum keluar kas, tapi diperhitungkan sebagai HPP
            $totalModal = 0;
            if ($detail->unit_id && $detail->modal_mobil > 0 && !$isRentToRent) {
                $totalModal = $detail->modal_mobil * ($detail->lama_sewa > 0 ? $detail->lama_sewa : 1);
            }

            $totalPengeluaran = $totalRentToRent + $totalOperasional + $totalCardNet + $totalModal;
        }

        $margin = $totalBiaya - $totalPengeluaran;

        // Dibuat Oleh: kalau unit detail ini sama dengan unit initial, kredit ke pembuat initial.
        // Kalau beda (mis. extend/rolling pindah unit), kredit ke pembuat detail ini sendiri.
        $dibuatOlehUser = $initialDetail?->createdBy;
        if ($initialDetail && $detail->unit_id !== $initialDetail->unit_id) {
            $dibuatOlehUser = $detail->createdBy;
        }

        return [
            'id' => $this->id,
            'row_id' => $detail->id,
            'row_type' => $detail->detail_type,
            'kode_booking' => $this->kode_booking,
            'status' => $this->status,
            'kota' => $this->kota,
            'tujuan' => $this->tujuan,
            'customer' => [
                'id' => $this->customer?->id,
                'nama' => $this->customer?->nama ?? '-',
                'status' => $this->customer?->status ?? 'non-member',
            ],
            'unit' => $unitInfo,
            'periode' => $periodeInfo,
            'total_biaya'           => $totalBiaya,
            'sisa_tagihan'          => (int) ($this->cached_sisa_tagihan ?? 0),
            'total_rent_to_rent'    => $totalRentToRent,
            // Operasional: dana net yang keluar ke driver + realisasi langsung
            'total_operasional'     => $totalOperasional,
            // Breakdown detail
            'total_fund_disbursed'  => $totalFundDisbursed,
            'total_fund_returned'   => $totalFundReturned,
            'total_bon_approved'    => $totalBonApproved,
            'total_direct_expense'  => $totalDirectExpense,
            // Kartu Fleet (terpisah dari deposit operasional)
            'total_card_topup'      => $totalCardTopup,
            'total_card_return'     => $totalCardReturn,
            'total_card_bon'        => $totalCardBon,
            'total_modal'           => $totalModal,
            'total_pengeluaran'     => $totalPengeluaran,
            'margin'                => $margin,
            'handled_by'            => $this->handledBy ? [
                'id'   => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ] : null,
            'dibuat_oleh'           => $dibuatOlehUser ? [
                'id'   => $dibuatOlehUser->id,
                'name' => $dibuatOlehUser->name,
            ] : null,
        ];
    }

    private function computeDetailRevenue(BookingDetail $d): int
    {
        $duration = $d->lama_sewa ?? 1;
        $total = 0;

        if ($d->pricing_mode === 'all_in') {
            $total += (int) (($d->harga_all_in ?? 0) * $duration);

            $total += $d->costs->sum(fn($cost) =>
                $cost->type === 'diskon'
                    ? -((int) $cost->amount)
                    : ((bool) $cost->is_additional ? (int) $cost->amount : 0)
            );
        } else {
            $total += app(BookingBillingService::class)->detailRentalSubtotal($d);

            $total += $d->costs->sum(fn($cost) =>
                $cost->type === 'diskon' ? -((int) $cost->amount) : (int) $cost->amount
            );
        }

        return $total;
    }
}
