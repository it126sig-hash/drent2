<?php

namespace App\Services;

use App\Models\RentToRentDebt;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class LegacyRentToRentPaymentImportService
{
    private array $paymentAccountMap = [];
    private array $touchedDebtIds = [];
    private array $processedDebtIds = [];
    private array $previewRows = [];
    private array $skippedRows = [];
    private array $overrideRows = [];

    public function import(string $sourceDatabase, int $userId, bool $dryRun = false): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $this->assertRequiredTablesExist($sourceDatabase);
        $this->loadMaps($sourceDatabase);
        $this->resetBuffers();

        $runner = function () use ($sourceDatabase, $userId, $dryRun) {
            $result = [
                'legacy_bagi_hasil_total' => 0,
                'legacy_payment_rows' => 0,
                'legacy_payment_total' => 0,
                'eligible_rows' => 0,
                'eligible_payment_rows' => 0,
                'eligible_payment_total' => 0,
                'payments_created' => 0,
                'payments_updated' => 0,
                'payments_skipped_zero_amount' => 0,
                'debt_overrides_changed' => 0,
                'debt_overrides_unchanged' => 0,
                'skipped_missing_booking_map' => 0,
                'skipped_missing_detail_map' => 0,
                'skipped_missing_debt' => 0,
                'skipped_missing_payment_account' => 0,
                'skipped_missing_payment_record' => 0,
                'report_directory' => '',
            ];

            $rows = $this->loadRows($sourceDatabase);
            $result['legacy_bagi_hasil_total'] = $rows->count();
            $result['legacy_payment_rows'] = $rows->filter(fn (array $row) => $this->money($row['pembayaran'] ?? null) > 0)->count();
            $result['legacy_payment_total'] = $rows->sum(fn (array $row) => $this->money($row['pembayaran'] ?? null));

            foreach ($rows as $row) {
                $amount = $this->money($row['pembayaran'] ?? null);
                $debtAmount = $this->money($row['basil'] ?? null) + $this->money($row['basil_extend'] ?? null);
                $skipReason = $this->skipReason($row, $amount);

                if ($skipReason) {
                    $result[$skipReason]++;
                    $this->skippedRows[] = $this->skippedReportRow($row, $skipReason, $amount, $debtAmount);
                    continue;
                }

                $result['eligible_rows']++;
                $debtId = (int) $row['rent_to_rent_debt_id'];
                $this->syncDebtAmount($sourceDatabase, $row, $debtAmount, $dryRun, $result);

                if ($amount <= 0) {
                    $result['payments_skipped_zero_amount']++;
                    continue;
                }

                $paymentAccountId = $this->paymentAccountMap[(string) $row['id_payment']] ?? null;
                if (! $paymentAccountId) {
                    $result['skipped_missing_payment_account']++;
                    $this->skippedRows[] = $this->skippedReportRow($row, 'skipped_missing_payment_account', $amount, $debtAmount);
                    continue;
                }

                $result['eligible_payment_rows']++;
                $result['eligible_payment_total'] += $amount;

                $paymentMap = $this->findMap($sourceDatabase, 'bagi_hasil', (string) $row['kode_bagi_hasil']);
                $paymentId = $paymentMap?->target_id;
                $paymentExists = $paymentId ? DB::table('rent_to_rent_payments')->where('id', $paymentId)->exists() : false;

                $this->previewRows[] = $this->previewReportRow($row, $amount, $debtAmount, $paymentAccountId, $paymentExists);

                if ($dryRun) {
                    $result[$paymentExists ? 'payments_updated' : 'payments_created']++;
                    continue;
                }

                $this->createOrUpdatePayment($sourceDatabase, $row, $debtId, $paymentId, $paymentExists, $paymentAccountId, $amount, $userId);
                $result[$paymentExists ? 'payments_updated' : 'payments_created']++;
                $this->touchedDebtIds[$debtId] = true;
            }

            if (! $dryRun) {
                $this->syncTouchedDebts();
            }

            $result['report_directory'] = $this->writeReports($sourceDatabase, $result);

            return $result;
        };

        return $dryRun ? $runner() : DB::transaction($runner);
    }

    private function syncDebtAmount(string $sourceDatabase, array $row, int $debtAmount, bool $dryRun, array &$result): void
    {
        if ($debtAmount <= 0) {
            return;
        }

        $debtId = (int) $row['rent_to_rent_debt_id'];
        if (isset($this->processedDebtIds[$debtId])) {
            return;
        }

        $this->processedDebtIds[$debtId] = true;
        $current = $row['amount_override'] !== null ? (int) $row['amount_override'] : null;
        $changed = $current !== $debtAmount;

        $this->overrideRows[] = [
            'kode_bagi_hasil' => $row['kode_bagi_hasil'],
            'kode_transaksi' => $row['kode_transaksi'],
            'detail_id' => $row['detail_id'],
            'booking_detail_id' => $row['booking_detail_id'],
            'rent_to_rent_debt_id' => $debtId,
            'current_amount_override' => $current,
            'legacy_basil' => $this->money($row['basil'] ?? null),
            'legacy_basil_extend' => $this->money($row['basil_extend'] ?? null),
            'legacy_debt_amount' => $debtAmount,
            'changed' => $changed ? 'yes' : 'no',
        ];

        $result[$changed ? 'debt_overrides_changed' : 'debt_overrides_unchanged']++;

        if ($dryRun || ! $changed) {
            return;
        }

        DB::table('rent_to_rent_debts')
            ->where('id', $debtId)
            ->update([
                'amount_override' => $debtAmount,
                'updated_at' => now(),
            ]);

        $this->touchedDebtIds[$debtId] = true;

        $this->upsertMap($sourceDatabase, 'bagi_hasil_debt_amount', (string) $row['detail_id'], 'rent_to_rent_debts', $debtId, 'sync_legacy_bagi_hasil_amount', 100, [
            'kode_transaksi' => $row['kode_transaksi'],
            'kode_bagi_hasil' => $row['kode_bagi_hasil'],
            'booking_detail_id' => $row['booking_detail_id'],
            'legacy_basil' => $this->money($row['basil'] ?? null),
            'legacy_basil_extend' => $this->money($row['basil_extend'] ?? null),
            'amount_override' => $debtAmount,
            'source' => 'legacy_rent_to_rent_payment_import',
        ]);
    }

    private function createOrUpdatePayment(
        string $sourceDatabase,
        array $row,
        int $debtId,
        ?int $paymentId,
        bool $paymentExists,
        int $paymentAccountId,
        int $amount,
        int $userId
    ): void {
        $paidAt = $this->dateOrNull($row['tanggal_pembayaran'] ?? null)
            ?: $this->dateOrNull($row['bagi_hasil_created_at'] ?? null)
            ?: now();

        $payload = [
            'rent_to_rent_bill_id' => null,
            'payment_account_id' => $paymentAccountId,
            'amount' => $amount,
            'status' => 'active',
            'paid_at' => $paidAt,
            'voided_at' => null,
            'created_by' => $userId,
        ];

        if ($paymentExists) {
            DB::table('rent_to_rent_payments')->where('id', $paymentId)->update($payload + ['updated_at' => now()]);
        } else {
            $paymentId = DB::table('rent_to_rent_payments')->insertGetId($payload + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('rent_to_rent_payment_allocations')
            ->where('rent_to_rent_payment_id', $paymentId)
            ->delete();

        DB::table('rent_to_rent_payment_allocations')->insert([
            'rent_to_rent_payment_id' => $paymentId,
            'rent_to_rent_bill_item_id' => null,
            'rent_to_rent_debt_id' => $debtId,
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->upsertMap($sourceDatabase, 'bagi_hasil', (string) $row['kode_bagi_hasil'], 'rent_to_rent_payments', $paymentId, 'import_rent_to_rent_payment', 100, [
            'kode_transaksi' => $row['kode_transaksi'],
            'detail_id' => $row['detail_id'],
            'booking_id' => $row['booking_id'],
            'booking_detail_id' => $row['booking_detail_id'],
            'rent_to_rent_debt_id' => $debtId,
            'amount' => $amount,
            'payment_account_id' => $paymentAccountId,
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'dilunaskan' => (int) ($row['dilunaskan'] ?? 0),
            'keterangan' => $row['keterangan'],
            'source' => 'legacy_rent_to_rent_payment_import',
        ]);
    }

    private function syncTouchedDebts(): void
    {
        $service = app(RentToRentService::class);

        foreach (array_keys($this->touchedDebtIds) as $debtId) {
            $debt = RentToRentDebt::query()
                ->with([
                    'bookingDetail.unit',
                    'billItems.bill',
                    'billItems.allocations.payment',
                    'paymentAllocations.payment',
                ])
                ->find($debtId);

            if (! $debt) {
                continue;
            }

            $debt->update(['status' => $service->paymentStatusForDebt($debt)]);
            $service->refreshDebtCache($debt->fresh([
                'bookingDetail.unit',
                'billItems.bill',
                'billItems.allocations.payment',
                'paymentAllocations.payment',
            ]));
        }
    }

    private function skipReason(array $row, int $amount): ?string
    {
        if (! $row['booking_id']) {
            return 'skipped_missing_booking_map';
        }

        if (! $row['booking_detail_id']) {
            return 'skipped_missing_detail_map';
        }

        if (! $row['rent_to_rent_debt_id']) {
            return 'skipped_missing_debt';
        }

        if ($amount > 0 && ! $this->filled($row['id_payment'] ?? null)) {
            return 'skipped_missing_payment_record';
        }

        return null;
    }

    private function loadRows(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                bh.kode_bagi_hasil,
                bh.kode_transaksi,
                bh.id AS detail_id,
                bh.penerimaan,
                bh.tanggal_pembayaran,
                bh.pembayaran,
                bh.id_payment,
                bh.date_add AS bagi_hasil_created_at,
                bh.keterangan,
                bh.dilunaskan,
                t.kode_detail_transaksi,
                d.basil,
                d.basil_extend,
                d.has_bagi_hasil,
                bm.target_id AS booking_id,
                dm.target_id AS booking_detail_id,
                rtd.id AS rent_to_rent_debt_id,
                rtd.amount_override,
                rtd.cached_total_amount,
                rtd.cached_paid_amount
            FROM {$this->table($sourceDatabase, 'bagi_hasil')} bh
            LEFT JOIN {$this->table($sourceDatabase, 'transaksi')} t
                ON t.kode_transaksi = bh.kode_transaksi
            LEFT JOIN {$this->table($sourceDatabase, 'detail_transaksi')} d
                ON d.kode_detail_transaksi = t.kode_detail_transaksi
            LEFT JOIN legacy_migration_maps bm
                ON bm.source_database = ?
                AND bm.legacy_table = 'transaksi'
                AND bm.legacy_id = bh.kode_transaksi
                AND bm.target_table = 'bookings'
                AND bm.target_id IS NOT NULL
            LEFT JOIN legacy_migration_maps dm
                ON dm.source_database = ?
                AND dm.legacy_table = 'detail_transaksi'
                AND dm.legacy_id = CAST(d.id AS CHAR)
                AND dm.target_table = 'booking_details'
                AND dm.target_id IS NOT NULL
            LEFT JOIN rent_to_rent_debts rtd
                ON rtd.booking_detail_id = dm.target_id
            ORDER BY bh.date_add, bh.kode_bagi_hasil
        ", [$sourceDatabase, $sourceDatabase]))->map(fn ($row) => (array) $row);
    }

    private function loadMaps(string $sourceDatabase): void
    {
        $this->paymentAccountMap = DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', 'payment_type')
            ->where('target_table', 'payment_accounts')
            ->whereNotNull('target_id')
            ->pluck('target_id', 'legacy_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function findMap(string $sourceDatabase, string $legacyTable, string $legacyId): ?object
    {
        return DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->first();
    }

    private function upsertMap(string $sourceDatabase, string $legacyTable, string $legacyId, ?string $targetTable, ?int $targetId, string $decision, int $score, array $metadata = []): void
    {
        DB::table('legacy_migration_maps')->upsert([[
            'source_database' => $sourceDatabase,
            'legacy_table' => $legacyTable,
            'legacy_id' => $legacyId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'canonical_legacy_id' => null,
            'decision' => $decision,
            'confidence_score' => $score,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['source_database', 'legacy_table', 'legacy_id'], [
            'target_table',
            'target_id',
            'decision',
            'confidence_score',
            'metadata',
            'updated_at',
        ]);
    }

    private function writeReports(string $sourceDatabase, array $result): string
    {
        $directory = 'legacy-migration/reports';
        Storage::disk('local')->makeDirectory($directory);
        $reportPath = Storage::disk('local')->path($directory);

        $this->writeCsv($directory.'/rent_to_rent_payment_import_preview.csv', $this->previewRows);
        $this->writeCsv($directory.'/rent_to_rent_payment_skipped.csv', $this->skippedRows);
        $this->writeCsv($directory.'/rent_to_rent_debt_amount_overrides.csv', $this->overrideRows);
        Storage::disk('local')->put(
            $directory.'/rent_to_rent_payment_summary.json',
            json_encode([
                'source_database' => $sourceDatabase,
                'generated_at' => now()->toISOString(),
                'summary' => $result + ['report_directory' => $reportPath],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $reportPath;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen('php://temp', 'w+');
        $headers = array_keys($rows[0] ?? ['empty' => null]);
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? null, $headers));
        }

        rewind($handle);
        Storage::disk('local')->put($path, stream_get_contents($handle));
        fclose($handle);
    }

    private function previewReportRow(array $row, int $amount, int $debtAmount, int $paymentAccountId, bool $paymentExists): array
    {
        return [
            'kode_bagi_hasil' => $row['kode_bagi_hasil'],
            'kode_transaksi' => $row['kode_transaksi'],
            'detail_id' => $row['detail_id'],
            'booking_id' => $row['booking_id'],
            'booking_detail_id' => $row['booking_detail_id'],
            'rent_to_rent_debt_id' => $row['rent_to_rent_debt_id'],
            'legacy_debt_amount' => $debtAmount,
            'payment_amount' => $amount,
            'payment_account_id' => $paymentAccountId,
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'dilunaskan' => $row['dilunaskan'],
            'action' => $paymentExists ? 'update' : 'create',
        ];
    }

    private function skippedReportRow(array $row, string $reason, int $amount, int $debtAmount): array
    {
        return [
            'reason' => $reason,
            'kode_bagi_hasil' => $row['kode_bagi_hasil'] ?? null,
            'kode_transaksi' => $row['kode_transaksi'] ?? null,
            'detail_id' => $row['detail_id'] ?? null,
            'booking_id' => $row['booking_id'] ?? null,
            'booking_detail_id' => $row['booking_detail_id'] ?? null,
            'rent_to_rent_debt_id' => $row['rent_to_rent_debt_id'] ?? null,
            'legacy_debt_amount' => $debtAmount,
            'payment_amount' => $amount,
            'id_payment' => $row['id_payment'] ?? null,
            'tanggal_pembayaran' => $row['tanggal_pembayaran'] ?? null,
            'dilunaskan' => $row['dilunaskan'] ?? null,
        ];
    }

    private function resetBuffers(): void
    {
        $this->touchedDebtIds = [];
        $this->processedDebtIds = [];
        $this->previewRows = [];
        $this->skippedRows = [];
        $this->overrideRows = [];
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $requiredTables = ['bagi_hasil', 'transaksi', 'detail_transaksi'];
        $existing = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$sourceDatabase]
        ))->pluck('TABLE_NAME')->all();

        $missing = array_values(array_diff($requiredTables, $existing));

        if ($missing !== []) {
            throw new InvalidArgumentException('Tabel legacy tidak lengkap: '.implode(', ', $missing));
        }
    }

    private function validateDatabaseName(string $database): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException('Nama database legacy tidak valid.');
        }

        return $database;
    }

    private function table(string $database, string $table): string
    {
        return "`{$database}`.`{$table}`";
    }

    private function money(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) $value);
    }

    private function filled(mixed $value): bool
    {
        return ! in_array(trim((string) $value), ['', '-', '0'], true);
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if (! $this->filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
