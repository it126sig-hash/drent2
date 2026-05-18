<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncReceivableCache extends Command
{
    protected $signature = 'receivable:sync-cache
                            {--chunk=200 : Jumlah booking per batch}
                            {--id= : Sync hanya satu booking_id tertentu}';

    protected $description = 'Sync ulang kolom cached_sisa_tagihan di tabel bookings';

    /**
     * SQL chunk UPDATE — formula sesuai BookingBillingService:
     *
     * totalTagihan per detail:
     *   mode normal  : (harga_mobil - diskon_mobil) * lama_sewa + SUM(costs biaya) - SUM(costs diskon)
     *   mode all_in  : harga_all_in * lama_sewa + SUM(costs is_additional biaya) - SUM(costs diskon)
     *
     * paidAmount: SUM(payments.amount) WHERE status != 'voided'
     *
     * cached_sisa_tagihan = MAX(0, totalTagihan - paidAmount)
     *
     * Catatan tabel:
     *   - booking_costs  : tidak punya deleted_at (no soft delete)
     *   - booking_payments: tidak punya deleted_at (no soft delete)
     *   - booking_details : punya deleted_at
     */
    private string $chunkSql = <<<'SQL'
        UPDATE bookings b
        SET cached_sisa_tagihan = GREATEST(0,

            COALESCE((
                SELECT SUM(
                    CASE
                        WHEN bd.pricing_mode = 'all_in' THEN
                            (COALESCE(bd.harga_all_in, 0) * COALESCE(bd.lama_sewa, 1))
                            + COALESCE((
                                SELECT SUM(
                                    CASE
                                        WHEN bc.type = 'diskon' THEN -(bc.amount)
                                        WHEN bc.is_additional = 1 AND bc.type != 'diskon' THEN bc.amount
                                        ELSE 0
                                    END
                                )
                                FROM booking_costs bc
                                WHERE bc.booking_detail_id = bd.id
                            ), 0)
                        ELSE
                            ((COALESCE(bd.harga_mobil, 0) - COALESCE(bd.diskon_mobil, 0)) * COALESCE(bd.lama_sewa, 1))
                            + COALESCE((
                                SELECT SUM(
                                    CASE
                                        WHEN bc.type = 'diskon' THEN -(bc.amount)
                                        ELSE bc.amount
                                    END
                                )
                                FROM booking_costs bc
                                WHERE bc.booking_detail_id = bd.id
                            ), 0)
                    END
                )
                FROM booking_details bd
                WHERE bd.booking_id = b.id
                  AND bd.status NOT IN ('batal')
                  AND bd.deleted_at IS NULL
            ), 0)

            -

            COALESCE((
                SELECT SUM(bp.amount)
                FROM booking_payments bp
                WHERE bp.booking_id = b.id
                  AND COALESCE(bp.status, 'active') != 'voided'
            ), 0)

        )
        WHERE b.id IN (%s)
SQL;

    public function handle(): int
    {
        $specificId = $this->option('id');
        $chunkSize  = (int) $this->option('chunk');

        $this->info('Sync cached_sisa_tagihan...');

        if ($specificId) {
            $this->syncIds([$specificId]);
            $result = DB::selectOne('SELECT cached_sisa_tagihan FROM bookings WHERE id = ?', [$specificId]);
            $this->info("Booking #{$specificId} → " . number_format($result->cached_sisa_tagihan));
            return self::SUCCESS;
        }

        $total = DB::selectOne('SELECT COUNT(*) as c FROM bookings WHERE deleted_at IS NULL')->c;
        $this->info("Total booking: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::table('bookings')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($bar) {
                $ids = $rows->pluck('id')->toArray();
                $this->syncIds($ids);
                $bar->advance(count($ids));
            });

        $bar->finish();
        $this->newLine(2);

        // Statistik akhir
        $stats = DB::selectOne(
            'SELECT COUNT(*) total,
                    SUM(cached_sisa_tagihan > 0) with_sisa,
                    SUM(cached_sisa_tagihan = 0) zero
             FROM bookings WHERE deleted_at IS NULL'
        );

        $this->table(
            ['Total', 'Ada Sisa Tagihan', 'Lunas / Nol'],
            [[$stats->total, $stats->with_sisa, $stats->zero]]
        );

        return self::SUCCESS;
    }

    private function syncIds(array $ids): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        DB::update(sprintf($this->chunkSql, $placeholders), $ids);
    }
}
