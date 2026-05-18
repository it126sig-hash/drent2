<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyTransactionImportService
{
    private array $customerMap = [];
    private array $unitMap = [];
    private array $driverMap = [];
    private array $paymentAccountMap = [];
    private array $dryRunImportedTransactions = [];

    public function import(string $sourceDatabase, int $tenantId, int $branchId, int $userId, bool $dryRun = false): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $this->assertRequiredTablesExist($sourceDatabase);
        $this->loadMaps($sourceDatabase);
        $this->dryRunImportedTransactions = [];

        return DB::transaction(function () use ($sourceDatabase, $tenantId, $branchId, $userId, $dryRun) {
            $result = [
                'payment_accounts_created' => 0,
                'payment_accounts_updated' => 0,
                'payment_accounts_linked' => 0,
                'eligible_details' => 0,
                'bookings_created' => 0,
                'bookings_updated' => 0,
                'details_created' => 0,
                'details_updated' => 0,
                'costs_created' => 0,
                'payments_created' => 0,
                'payments_updated' => 0,
                'skipped_missing_customer' => 0,
                'skipped_missing_unit' => 0,
                'skipped_missing_driver' => 0,
                'skipped_invalid_dates' => 0,
                'skipped_payment_missing_account' => 0,
                'dp_total' => 0,
                'cicilan_total' => 0,
                'pelunasan_total' => 0,
            ];

            $accountResult = $this->importPaymentAccounts($sourceDatabase, $tenantId, $branchId, $dryRun);
            foreach ($accountResult as $key => $value) {
                $result['payment_accounts_'.$key] = $value;
            }

            $rows = $this->loadTransactionDetails($sourceDatabase);

            foreach ($rows as $row) {
                $result['eligible_details']++;
                $skipReason = $this->skipReason($row);

                if ($skipReason) {
                    $result[$skipReason]++;

                    if (! $dryRun) {
                        $this->upsertTransactionSkipMap($sourceDatabase, $row, $skipReason);
                    }

                    continue;
                }

                $bookingMap = $this->findMap($sourceDatabase, 'transaksi', $row['kode_transaksi']);
                $bookingId = $bookingMap?->target_id;
                $bookingExists = $bookingId ? DB::table('bookings')->where('id', $bookingId)->exists() : false;

                if (! $bookingExists) {
                    $existing = DB::table('bookings')->where('kode_booking', $row['kode_transaksi'])->first();
                    $bookingId = $existing?->id;
                    $bookingExists = (bool) $existing;
                }

                $bookingPayload = $this->bookingPayload($tenantId, $branchId, $userId, $row);

                if ($dryRun) {
                    $result[$bookingExists ? 'bookings_updated' : 'bookings_created']++;
                } elseif ($bookingExists) {
                    DB::table('bookings')->where('id', $bookingId)->update($bookingPayload + ['updated_at' => now()]);
                    $result['bookings_updated']++;
                } else {
                    $bookingId = DB::table('bookings')->insertGetId($bookingPayload + [
                        'created_at' => $this->dateOrNow($row['transaksi_created_at'] ?? null),
                        'updated_at' => $this->dateOrNow($row['transaksi_updated_at'] ?? null),
                    ]);
                    $result['bookings_created']++;
                }

                if (! $dryRun) {
                    $this->upsertMap($sourceDatabase, 'transaksi', $row['kode_transaksi'], 'bookings', $bookingId, 'import_transaction', 100, [
                        'kode_detail_transaksi' => $row['kode_detail_transaksi'],
                        'source' => 'legacy_transaction_import',
                    ]);
                } else {
                    $this->dryRunImportedTransactions[$row['kode_transaksi']] = true;
                }

                $detailMap = $this->findMap($sourceDatabase, 'detail_transaksi', (string) $row['detail_id']);
                $detailId = $detailMap?->target_id;
                $detailExists = $detailId ? DB::table('booking_details')->where('id', $detailId)->exists() : false;
                $detailPayload = $this->bookingDetailPayload($dryRun ? 0 : $bookingId, $row);

                if ($dryRun) {
                    $result[$detailExists ? 'details_updated' : 'details_created']++;
                    $result['costs_created'] += count($this->costRows(0, $row));
                } elseif ($detailExists) {
                    DB::table('booking_details')->where('id', $detailId)->update($detailPayload + ['updated_at' => now()]);
                    $this->replaceLegacyCosts($detailId, $row);
                    $result['details_updated']++;
                    $result['costs_created'] += count($this->costRows($detailId, $row));
                } else {
                    $detailId = DB::table('booking_details')->insertGetId($detailPayload + [
                        'created_at' => $this->dateOrNow($row['detail_created_at'] ?? null),
                        'updated_at' => $this->dateOrNow($row['detail_updated_at'] ?? null),
                    ]);
                    $this->replaceLegacyCosts($detailId, $row);
                    $result['details_created']++;
                    $result['costs_created'] += count($this->costRows($detailId, $row));
                }

                if (! $dryRun) {
                    $this->upsertMap($sourceDatabase, 'detail_transaksi', (string) $row['detail_id'], 'booking_details', $detailId, 'import_transaction_detail', 100, [
                        'kode_transaksi' => $row['kode_transaksi'],
                        'kode_mobil' => $row['kode_mobil'],
                        'kode_supir' => $row['kode_supir'],
                    ]);
                }

                $this->importDetailPayments($sourceDatabase, $row, $dryRun ? 0 : $bookingId, $userId, $dryRun, $result);
            }

            $this->importPiutangPayments($sourceDatabase, $userId, $dryRun, $result);

            return $result;
        });
    }

    private function importPaymentAccounts(string $sourceDatabase, int $tenantId, int $branchId, bool $dryRun): array
    {
        $result = ['created' => 0, 'updated' => 0, 'linked' => 0];
        $rows = DB::table($sourceDatabase.'.payment_type')->get();

        foreach ($rows as $row) {
            $legacyId = (string) $row->id_payment;
            $map = $this->findMap($sourceDatabase, 'payment_type', $legacyId);
            $targetId = $map?->target_id;
            $existing = $targetId ? DB::table('payment_accounts')->where('id', $targetId)->first() : null;
            $payload = [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'nama_bank' => mb_substr('Legacy '.$this->text($row->name ?? null, 'Payment '.$legacyId, 90), 0, 100),
                'nomor_rekening' => 'legacy-'.$legacyId,
                'atas_nama' => 'Legacy '.$sourceDatabase,
                'is_active' => true,
            ];
            $decision = 'import_payment_type';

            if (! $existing) {
                $existing = DB::table('payment_accounts')
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branchId)
                    ->where('nomor_rekening', $payload['nomor_rekening'])
                    ->first();

                if ($existing) {
                    $targetId = $existing->id;
                    $decision = 'link_existing_payment_type';
                }
            }

            if ($dryRun) {
                $result[$existing ? ($decision === 'link_existing_payment_type' ? 'linked' : 'updated') : 'created']++;
                $this->paymentAccountMap[$legacyId] = $targetId ?: -((int) $legacyId);
                continue;
            }

            if ($existing && $decision !== 'link_existing_payment_type') {
                DB::table('payment_accounts')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
                $result['updated']++;
            } elseif ($existing) {
                $result['linked']++;
            } else {
                $targetId = DB::table('payment_accounts')->insertGetId($payload + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $result['created']++;
            }

            $this->paymentAccountMap[$legacyId] = $targetId;
            $this->upsertMap($sourceDatabase, 'payment_type', $legacyId, 'payment_accounts', $targetId, $decision, 100, [
                'name' => $row->name,
                'payment_type' => $row->payment_type,
            ]);
        }

        return $result;
    }

    private function importDetailPayments(string $sourceDatabase, array $row, int $bookingId, int $userId, bool $dryRun, array &$result): void
    {
        $payments = [
            [
                'legacy_table' => 'detail_transaksi_payment',
                'legacy_id' => $row['detail_id'].':dp',
                'amount' => $this->money($row['dp'] ?? null),
                'account_legacy_id' => $row['id_payment_dp'] ?? null,
                'payment_type' => 'dp',
                'paid_at' => $row['transaksi_tanggal_faktur'] ?? $row['detail_created_at'] ?? $row['tgl_sewa'],
                'catatan' => 'DP legacy detail_transaksi '.$row['detail_id'],
                'total_key' => 'dp_total',
            ],
            [
                'legacy_table' => 'detail_transaksi_payment',
                'legacy_id' => $row['detail_id'].':pelunasan',
                'amount' => $this->money($row['pelunasan'] ?? null),
                'account_legacy_id' => $row['id_payment_pelunasan'] ?? null,
                'payment_type' => 'pelunasan',
                'paid_at' => $row['tanggal_pelunasan'] ?? $row['detail_updated_at'] ?? $row['tgl_kembali'],
                'catatan' => 'Pelunasan legacy detail_transaksi '.$row['detail_id'],
                'total_key' => 'pelunasan_total',
            ],
        ];

        foreach ($payments as $payment) {
            if ($payment['amount'] <= 0) {
                continue;
            }

            $paymentAccountId = $this->paymentAccountMap[(string) $payment['account_legacy_id']] ?? null;

            if (! $paymentAccountId) {
                $result['skipped_payment_missing_account']++;
                continue;
            }

            $result[$payment['total_key']] += $payment['amount'];
            $this->createOrUpdatePayment($sourceDatabase, $bookingId, $userId, $payment, $paymentAccountId, $dryRun, $result);
        }
    }

    private function importPiutangPayments(string $sourceDatabase, int $userId, bool $dryRun, array &$result): void
    {
        $rows = DB::table($sourceDatabase.'.piutang')->get();

        foreach ($rows as $row) {
            $amount = $this->money($row->pembayaran ?? null);
            if ($amount <= 0) {
                continue;
            }

            $bookingMap = $this->findMap($sourceDatabase, 'transaksi', (string) $row->kode_transaksi);
            if (! $bookingMap?->target_id && ! ($dryRun && isset($this->dryRunImportedTransactions[(string) $row->kode_transaksi]))) {
                continue;
            }

            $paymentAccountId = $this->paymentAccountMap[(string) $row->id_payment] ?? null;
            if (! $paymentAccountId) {
                $result['skipped_payment_missing_account']++;
                continue;
            }

            $result['cicilan_total'] += $amount;
            $this->createOrUpdatePayment($sourceDatabase, $dryRun ? 0 : (int) $bookingMap->target_id, $userId, [
                'legacy_table' => 'piutang',
                'legacy_id' => (string) $row->kode_piutang,
                'amount' => $amount,
                'payment_type' => 'cicilan',
                'paid_at' => $row->tanggal_pembayaran ?? $row->date_add,
                'catatan' => 'Piutang/cicilan legacy '.$row->kode_piutang,
                'metadata' => [
                    'kode_transaksi' => $row->kode_transaksi,
                    'detail_id' => $row->id,
                ],
            ], $paymentAccountId, $dryRun, $result);
        }
    }

    private function createOrUpdatePayment(
        string $sourceDatabase,
        int $bookingId,
        int $userId,
        array $payment,
        int $paymentAccountId,
        bool $dryRun,
        array &$result
    ): void {
        $map = $this->findMap($sourceDatabase, $payment['legacy_table'], $payment['legacy_id']);
        $targetId = $map?->target_id;
        $exists = $targetId ? DB::table('booking_payments')->where('id', $targetId)->exists() : false;

        if ($dryRun) {
            $result[$exists ? 'payments_updated' : 'payments_created']++;
            return;
        }

        $payload = [
            'booking_id' => $bookingId,
            'payment_account_id' => $paymentAccountId,
            'amount' => $payment['amount'],
            'payment_type' => $payment['payment_type'],
            'status' => 'paid',
            'catatan' => $payment['catatan'],
            'paid_at' => $this->dateOrNull($payment['paid_at'] ?? null),
            'created_by' => $userId,
        ];

        if ($exists) {
            DB::table('booking_payments')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
            $result['payments_updated']++;
        } else {
            $targetId = DB::table('booking_payments')->insertGetId($payload + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $result['payments_created']++;
        }

        $this->upsertMap($sourceDatabase, $payment['legacy_table'], $payment['legacy_id'], 'booking_payments', $targetId, 'import_booking_payment', 100, [
            'payment_type' => $payment['payment_type'],
            'amount' => $payment['amount'],
            'payment_account_id' => $paymentAccountId,
            ...($payment['metadata'] ?? []),
        ]);
    }

    private function replaceLegacyCosts(int $detailId, array $row): void
    {
        DB::table('booking_costs')
            ->where('booking_detail_id', $detailId)
            ->where('keterangan', 'like', 'Legacy detail_transaksi '.$row['detail_id'].'%')
            ->delete();

        $costRows = $this->costRows($detailId, $row);

        if ($costRows !== []) {
            DB::table('booking_costs')->insert($costRows);
        }
    }

    private function costRows(int $detailId, array $row): array
    {
        $costs = [
            ['label' => 'Biaya Supir Legacy', 'amount' => $this->money($row['harga_supir'] ?? null), 'type' => 'biaya'],
            ['label' => 'Biaya Kenek Legacy', 'amount' => $this->money($row['harga_kenek'] ?? null), 'type' => 'biaya'],
            ['label' => 'BBM Legacy', 'amount' => $this->money($row['bbm'] ?? null), 'type' => 'biaya'],
            ['label' => 'Tol Legacy', 'amount' => $this->money($row['tol'] ?? null), 'type' => 'biaya'],
            ['label' => 'Uang Makan Legacy', 'amount' => $this->money($row['uang_makan'] ?? null), 'type' => 'biaya'],
            ['label' => 'Lain-lain Legacy', 'amount' => $this->money($row['lain_lain'] ?? null), 'type' => 'biaya'],
            ['label' => 'Denda Legacy', 'amount' => $this->money($row['denda'] ?? null), 'type' => 'biaya'],
            ['label' => 'Diskon Legacy', 'amount' => $this->money($row['diskon'] ?? null), 'type' => 'diskon'],
        ];

        return collect($costs)
            ->filter(fn (array $cost) => $cost['amount'] > 0)
            ->map(fn (array $cost) => [
                'booking_detail_id' => $detailId,
                'cost_type_id' => null,
                'type' => $cost['type'],
                'label' => $cost['label'],
                'amount' => $cost['amount'],
                'keterangan' => 'Legacy detail_transaksi '.$row['detail_id'].' ('.$row['kode_transaksi'].')',
                'is_additional' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();
    }

    private function bookingPayload(int $tenantId, int $branchId, int $userId, array $row): array
    {
        $returnedAt = $this->dateOrNull($row['tanggal_dikembalikan'] ?? null);
        $status = $returnedAt || (int) ($row['has_dikembalikan'] ?? 0) === 1 ? 'selesai' : 'rental_unit';
        $total = $this->detailTotal($row);
        $dp = $this->money($row['dp'] ?? null);

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'customer_id' => $this->customerMap[(string) $row['kode_pelanggan']] ?? null,
            'created_by' => $userId,
            'kode_booking' => $row['kode_transaksi'],
            'status' => $status,
            'lama_sewa' => $this->duration($row['lama_pemakaian'] ?? null),
            'paket_sewa' => $this->package($row['paket'] ?? null),
            'harga_dealing' => $total,
            'dp' => $dp > 0 ? $dp : null,
            'rekening_dp_id' => $dp > 0 ? ($this->paymentAccountMap[(string) ($row['id_payment_dp'] ?? '')] ?? null) : null,
            'tujuan' => $this->nullableText($row['tujuan'] ?? null, 255),
            'alamat_penjemputan' => $this->nullableText($row['alamat_penjemputan'] ?? null),
            'catatan' => $this->bookingNote($row),
            'confirmed_at' => $this->dateOrNull($row['transaksi_tanggal_faktur'] ?? null),
            'handled_at' => $this->dateOrNull($row['tgl_sewa'] ?? null),
            'checked_out_at' => $this->dateOrNull($row['tgl_sewa'] ?? null),
            'returned_at' => $returnedAt,
            'completed_at' => $status === 'selesai' ? ($returnedAt ?: $this->dateOrNull($row['tgl_kembali'] ?? null)) : null,
            'due_date' => $this->dateOrNull($row['tgl_kembali'] ?? null),
        ];
    }

    private function bookingDetailPayload(int $bookingId, array $row): array
    {
        return [
            'booking_id' => $bookingId,
            'unit_id' => $this->unitMap[(string) $row['kode_mobil']],
            'unit_placeholder' => null,
            'driver_id' => $this->driverTargetId($row['kode_supir'] ?? null),
            'tgl_sewa' => $this->dateOrNow($row['tgl_sewa'] ?? null),
            'tgl_kembali' => $this->dateOrNow($row['tgl_kembali'] ?? null),
            'harga_mobil' => $this->money($row['harga_sewa'] ?? null),
            'diskon_mobil' => 0,
            'lama_sewa' => $this->duration($row['lama_pemakaian'] ?? null),
            'paket_sewa' => $this->package($row['paket'] ?? null),
            'pricing_mode' => 'non_all_in',
            'pricing_package_id' => null,
            'harga_all_in' => null,
            'detail_type' => 'initial',
            'status' => $this->dateOrNull($row['tanggal_dikembalikan'] ?? null) || (int) ($row['has_dikembalikan'] ?? 0) === 1 ? 'selesai' : 'aktif',
        ];
    }

    private function detailTotal(array $row): int
    {
        $duration = $this->duration($row['lama_pemakaian'] ?? null);
        $rental = $this->money($row['harga_sewa'] ?? null) * $duration;
        $costs = $this->money($row['harga_supir'] ?? null)
            + $this->money($row['harga_kenek'] ?? null)
            + $this->money($row['bbm'] ?? null)
            + $this->money($row['tol'] ?? null)
            + $this->money($row['uang_makan'] ?? null)
            + $this->money($row['lain_lain'] ?? null)
            + $this->money($row['denda'] ?? null);

        return max(0, $rental + $costs - $this->money($row['diskon'] ?? null));
    }

    private function skipReason(array $row): ?string
    {
        if (! isset($this->customerMap[(string) $row['kode_pelanggan']])) {
            return 'skipped_missing_customer';
        }

        if (! isset($this->unitMap[(string) $row['kode_mobil']])) {
            return 'skipped_missing_unit';
        }

        if (! $this->dateOrNull($row['tgl_sewa'] ?? null) || ! $this->dateOrNull($row['tgl_kembali'] ?? null)) {
            return 'skipped_invalid_dates';
        }

        $driverCode = $this->blankToNull($row['kode_supir'] ?? null);
        if ($driverCode && ! $this->isNoDriverCode($driverCode) && ! array_key_exists($driverCode, $this->driverMap)) {
            return 'skipped_missing_driver';
        }

        return null;
    }

    private function upsertTransactionSkipMap(string $sourceDatabase, array $row, string $skipReason): void
    {
        $this->upsertMap($sourceDatabase, 'transaksi', $row['kode_transaksi'], 'bookings', null, $skipReason, 0, [
            'kode_detail_transaksi' => $row['kode_detail_transaksi'],
            'detail_id' => $row['detail_id'],
            'source' => 'legacy_transaction_import',
        ]);
    }

    private function loadMaps(string $sourceDatabase): void
    {
        $maps = DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->whereIn('legacy_table', ['data_pelanggan', 'data_mobil', 'data_supir', 'payment_type'])
            ->get();

        $this->customerMap = $maps->where('legacy_table', 'data_pelanggan')->whereNotNull('target_id')->pluck('target_id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
        $this->unitMap = $maps->where('legacy_table', 'data_mobil')->whereNotNull('target_id')->pluck('target_id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
        $this->driverMap = $maps->where('legacy_table', 'data_supir')->pluck('target_id', 'legacy_id')->map(fn ($id) => $id ? (int) $id : null)->all();
        $this->paymentAccountMap = $maps->where('legacy_table', 'payment_type')->whereNotNull('target_id')->pluck('target_id', 'legacy_id')->map(fn ($id) => (int) $id)->all();
    }

    private function loadTransactionDetails(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                t.kode_transaksi,
                t.kode_pelanggan,
                t.tanggal_faktur AS transaksi_tanggal_faktur,
                t.kode_detail_transaksi,
                t.date_add AS transaksi_created_at,
                t.date_mod AS transaksi_updated_at,
                b.kode_booking AS legacy_kode_booking,
                b.alamat_penjemputan,
                b.keterangan AS booking_keterangan,
                d.id AS detail_id,
                d.kode_mobil,
                d.kode_supir,
                d.tanggal_sewa AS tgl_sewa,
                d.tanggal_kembali AS tgl_kembali,
                d.tanggal_dikembalikan,
                d.lama_pemakaian,
                d.paket,
                d.harga_sewa,
                d.harga_supir,
                d.harga_kenek,
                d.harga_kenek,
                d.bbm,
                d.tol,
                d.uang_makan,
                d.lain_lain,
                d.diskon,
                d.dp,
                d.tanggal_pelunasan,
                d.pelunasan,
                d.denda,
                d.tujuan,
                d.note,
                d.has_lunas,
                d.has_dikembalikan,
                d.id_payment_dp,
                d.id_payment_pelunasan,
                d.date_add AS detail_created_at,
                d.date_mod AS detail_updated_at
            FROM {$this->table($sourceDatabase, 'transaksi')} t
            JOIN {$this->table($sourceDatabase, 'detail_transaksi')} d
                ON d.kode_detail_transaksi = t.kode_detail_transaksi
            LEFT JOIN {$this->table($sourceDatabase, 'booking')} b
                ON b.kode_transaksi = t.kode_transaksi
            ORDER BY t.tanggal_faktur, t.kode_transaksi, d.id
        "))->map(fn ($row) => (array) $row);
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $requiredTables = ['transaksi', 'detail_transaksi', 'piutang', 'payment_type', 'booking'];
        $existing = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$sourceDatabase]
        ))->pluck('TABLE_NAME')->all();

        $missing = array_values(array_diff($requiredTables, $existing));

        if ($missing !== []) {
            throw new InvalidArgumentException('Tabel legacy tidak lengkap: '.implode(', ', $missing));
        }
    }

    private function upsertMap(string $sourceDatabase, string $legacyTable, string $legacyId, ?string $targetTable, ?int $targetId, string $decision, int $score, array $metadata = []): void
    {
        DB::table('legacy_migration_maps')->upsert([
            [
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
            ],
        ], ['source_database', 'legacy_table', 'legacy_id'], [
            'target_table',
            'target_id',
            'decision',
            'confidence_score',
            'metadata',
            'updated_at',
        ]);
    }

    private function findMap(string $sourceDatabase, string $legacyTable, string $legacyId): ?object
    {
        return DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->first();
    }

    private function bookingNote(array $row): string
    {
        return collect([
            'Legacy transaksi: '.$row['kode_transaksi'],
            $row['legacy_kode_booking'] ? 'Legacy booking: '.$row['legacy_kode_booking'] : null,
            $this->nullableText($row['booking_keterangan'] ?? null),
            $this->nullableText($row['note'] ?? null),
        ])->filter()->implode(' | ');
    }

    private function driverTargetId(?string $driverCode): ?int
    {
        $driverCode = $this->blankToNull($driverCode);

        if (! $driverCode || $this->isNoDriverCode($driverCode)) {
            return null;
        }

        return $this->driverMap[$driverCode] ?? null;
    }

    private function isNoDriverCode(string $driverCode): bool
    {
        return $driverCode === 'DRV-2016050001';
    }

    private function package(?string $value): string
    {
        $value = strtolower((string) $value);

        if (str_contains($value, 'bulan')) {
            return 'bulanan';
        }

        if (str_contains($value, 'minggu')) {
            return 'mingguan';
        }

        return 'harian';
    }

    private function duration(mixed $value): int
    {
        return max(1, (int) round((float) $value));
    }

    private function money(mixed $value): int
    {
        return max(0, (int) round((float) $value));
    }

    private function text(?string $value, string $fallback, int $limit): string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            $value = $fallback;
        }

        return mb_substr($value, 0, $limit);
    }

    private function nullableText(?string $value, ?int $limit = null): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        return $limit ? mb_substr($value, 0, $limit) : $value;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' || $value === '0' ? null : $value;
    }

    private function dateOrNull(?string $date): ?Carbon
    {
        $date = trim((string) $date);

        if ($date === '' || str_starts_with($date, '0000-00-00') || str_starts_with($date, '-')) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);

            return $parsed->year < 1900 ? null : $parsed;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateOrNow(?string $date): Carbon
    {
        return $this->dateOrNull($date) ?: now();
    }

    private function validateDatabaseName(string $sourceDatabase): string
    {
        $sourceDatabase = trim($sourceDatabase);

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $sourceDatabase)) {
            throw new InvalidArgumentException('Nama database legacy hanya boleh berisi huruf, angka, underscore, dan dash.');
        }

        return $sourceDatabase;
    }

    private function table(string $database, string $table): string
    {
        return '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
    }
}
