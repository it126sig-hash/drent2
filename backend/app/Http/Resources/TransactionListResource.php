<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->bookingDetails->where('status', '!=', 'batal')->first()
            ?? $this->bookingDetails->first();

        $unitInfo = null;
        if ($detail) {
            $unit = $detail->unit;
            $rentalOwner = $unit?->rentalOwner;
            $isRentToRent = $rentalOwner ? !$rentalOwner->is_owner : false;

            $unitInfo = [
                'tipe' => $unit?->tipe ?? $detail->unit_placeholder ?? '-',
                'no_polisi' => $unit?->no_polisi ?? '-',
                'pemilik' => $rentalOwner?->nama ?? 'DRENT',
                'is_rent_to_rent' => $isRentToRent,
            ];
        }

        $periodeInfo = null;
        if ($detail) {
            $periodeInfo = [
                'tgl_sewa' => $detail->tgl_sewa,
                'tgl_kembali' => $detail->tgl_kembali,
                'paket' => $this->lama_sewa . ' x ' . ucfirst($this->paket_sewa),
                'tujuan' => $this->tujuan,
            ];
        }

        $totalBiaya         = $this->computeTotalTagihan();
        $totalRentToRent    = (int) ($this->total_rent_to_rent ?? 0);
        // Net operasional = dana yang diserahkan ke driver - sisa yang dikembalikan + pengeluaran langsung
        $totalFundDisbursed = (int) ($this->total_fund_disbursed ?? 0);
        $totalFundReturned  = (int) ($this->total_fund_returned ?? 0);
        $totalBonApproved   = (int) ($this->total_expense_approved ?? 0);
        $totalDirectExpense = (int) ($this->total_direct_expense ?? 0);
        $totalOperasional   = max(0, $totalFundDisbursed - $totalFundReturned) + $totalDirectExpense;

        // Modal unit internal (bukan R2R) — belum keluar kas, tapi diperhitungkan sebagai HPP
        $totalModal = 0;
        foreach ($this->bookingDetails->where('status', '!=', 'batal') as $d) {
            if ($d->unit_id && $d->modal_mobil > 0) {
                $rentalOwner = $d->unit?->rentalOwner;
                $isRentToRent = $rentalOwner ? !$rentalOwner->is_owner : false;
                if (!$isRentToRent) {
                    $totalModal += $d->modal_mobil * ($d->lama_sewa > 0 ? $d->lama_sewa : 1);
                }
            }
        }

        $totalPengeluaran   = $totalRentToRent + $totalOperasional + $totalModal;
        $margin             = $totalBiaya - $totalPengeluaran;

        return [
            'id' => $this->id,
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
            'total_modal'           => $totalModal,
            'total_pengeluaran'     => $totalPengeluaran,
            'margin'                => $margin,
        ];
    }

    private function computeTotalTagihan(): int
    {
        $billableDetails = $this->bookingDetails->where('status', '!=', 'batal');

        if ($billableDetails->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($billableDetails as $d) {
            $duration = $d->lama_sewa ?? 1;
            if ($d->pricing_mode === 'all_in') {
                $total += (int) (($d->harga_all_in ?? 0) * $duration);

                $total += $d->costs->sum(fn($cost) =>
                    $cost->type === 'diskon'
                        ? -((int) $cost->amount)
                        : ((bool) $cost->is_additional ? (int) $cost->amount : 0)
                );
            } else {
                $total += (int) (($d->harga_mobil - $d->diskon_mobil) * $duration);

                $total += $d->costs->sum(fn($cost) =>
                    $cost->type === 'diskon' ? -((int) $cost->amount) : (int) $cost->amount
                );
            }
        }

        return $total;
    }
}
