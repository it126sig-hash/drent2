<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyUnitImportService
{
    public function import(string $sourceDatabase, int $tenantId, int $branchId, bool $dryRun = false): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $this->assertRequiredTablesExist($sourceDatabase);

        return DB::transaction(function () use ($sourceDatabase, $tenantId, $branchId, $dryRun) {
            $units = $this->loadUnits($sourceDatabase);

            return $this->importUnits($sourceDatabase, $tenantId, $branchId, $units, $dryRun);
        });
    }

    private function importUnits(string $sourceDatabase, int $tenantId, int $branchId, Collection $units, bool $dryRun): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'linked_existing' => 0,
            'missing_owner_map' => 0,
            'fallback_plate' => 0,
        ];

        foreach ($units as $unit) {
            $ownerMap = $this->ownerMap($sourceDatabase, $unit['kode_pemilik'] ?? null);
            $payload = $this->unitPayload($tenantId, $branchId, $unit, $ownerMap?->target_id);
            $map = $this->findMap($sourceDatabase, 'data_mobil', $unit['kode_mobil']);
            $targetId = $map?->target_id;
            $existing = $targetId ? DB::table('units')->where('id', $targetId)->first() : null;
            $decision = 'import_unit';

            if (! $ownerMap?->target_id && $this->filled($unit['kode_pemilik'] ?? null)) {
                $result['missing_owner_map']++;
            }

            if (($payload['catatan'] ?? '') && str_contains($payload['catatan'], 'Nomor polisi legacy kosong')) {
                $result['fallback_plate']++;
            }

            if (! $existing) {
                $existing = DB::table('units')
                    ->where('tenant_id', $tenantId)
                    ->where('no_polisi', $payload['no_polisi'])
                    ->first();

                if ($existing) {
                    $targetId = $existing->id;
                    $decision = 'link_existing_unit_by_plate';
                }
            }

            if ($dryRun) {
                $result[$existing ? ($decision === 'link_existing_unit_by_plate' ? 'linked_existing' : 'updated') : 'created']++;
                continue;
            }

            if ($existing && $decision !== 'link_existing_unit_by_plate') {
                DB::table('units')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
                $result['updated']++;
            } elseif ($existing) {
                $result['linked_existing']++;
            } else {
                $targetId = DB::table('units')->insertGetId($payload + [
                    'created_at' => $this->dateOrNow($unit['date_add'] ?? null),
                    'updated_at' => $this->dateOrNow($unit['date_mod'] ?? null),
                ]);
                $result['created']++;
            }

            $this->upsertMap($sourceDatabase, $unit, $targetId, $decision, $payload);
        }

        return $result;
    }

    private function unitPayload(int $tenantId, int $branchId, array $unit, ?int $ownerId): array
    {
        $plate = $this->plate($unit['nomor_polisi'] ?? null);
        $catatan = ['Legacy: '.$unit['kode_mobil']];

        if (! $plate) {
            $plate = $unit['kode_mobil'];
            $catatan[] = 'Nomor polisi legacy kosong/tidak valid: '.($unit['nomor_polisi'] ?? '-');
        }

        if ($this->filled($unit['nomor_rangka'] ?? null)) {
            $catatan[] = 'Rangka: '.$unit['nomor_rangka'];
        }

        if ($this->filled($unit['nomor_mesin'] ?? null)) {
            $catatan[] = 'Mesin: '.$unit['nomor_mesin'];
        }

        if ($this->filled($unit['nomor_bpkb'] ?? null)) {
            $catatan[] = 'BPKB: '.$unit['nomor_bpkb'];
        }

        if ($this->filled($unit['bahan_bakar'] ?? null)) {
            $catatan[] = 'BBM: '.$unit['bahan_bakar'];
        }

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'rental_owner_id' => $ownerId,
            'tipe' => $this->text($unit['tipe_mobil'] ?? null, 'Unit '.$unit['kode_mobil'], 100),
            'merk' => $this->text($unit['jenis_mobil'] ?? null, 'Legacy', 100),
            'tahun' => $this->year($unit['tahun'] ?? null),
            'no_polisi' => $plate,
            'harga_1_hari' => $this->money($unit['harga_1hari'] ?? null),
            'harga_1_minggu' => $this->money($unit['harga_1minggu'] ?? null),
            'harga_1_bulan' => $this->money($unit['harga_1bulan'] ?? null),
            'modal_1_hari' => $this->money($unit['modal_1hari'] ?? null),
            'modal_1_minggu' => $this->money($unit['modal_1minggu'] ?? null),
            'modal_1_bulan' => $this->money($unit['modal_1bulan'] ?? null),
            'status' => ($unit['status'] ?? null) === 'out' ? 'Out' : 'Aktif',
            'catatan' => implode(' | ', $catatan),
        ];
    }

    private function upsertMap(string $sourceDatabase, array $unit, int $targetId, string $decision, array $payload): void
    {
        DB::table('legacy_migration_maps')->upsert([
            [
                'source_database' => $sourceDatabase,
                'legacy_table' => 'data_mobil',
                'legacy_id' => $unit['kode_mobil'],
                'target_table' => 'units',
                'target_id' => $targetId,
                'canonical_legacy_id' => null,
                'decision' => $decision,
                'confidence_score' => $decision === 'link_existing_unit_by_plate' ? 90 : 100,
                'metadata' => json_encode([
                    'tipe_mobil' => $unit['tipe_mobil'],
                    'jenis_mobil' => $unit['jenis_mobil'],
                    'nomor_polisi' => $unit['nomor_polisi'],
                    'mapped_no_polisi' => $payload['no_polisi'],
                    'kode_pemilik' => $unit['kode_pemilik'],
                    'source' => 'legacy_unit_import',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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

    private function ownerMap(string $sourceDatabase, ?string $ownerCode): ?object
    {
        if (! $this->filled($ownerCode)) {
            return null;
        }

        return $this->findMap($sourceDatabase, 'data_pemilik', trim((string) $ownerCode));
    }

    private function findMap(string $sourceDatabase, string $legacyTable, string $legacyId): ?object
    {
        return DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->first();
    }

    private function loadUnits(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                kode_mobil,
                kode_pemilik,
                status,
                tipe_mobil,
                jenis_mobil,
                nomor_polisi,
                nomor_rangka,
                nomor_mesin,
                nomor_bpkb,
                bahan_bakar,
                tahun,
                harga_1hari,
                harga_1minggu,
                harga_1bulan,
                modal_1hari,
                modal_1minggu,
                modal_1bulan,
                date_add,
                date_mod
            FROM {$this->table($sourceDatabase, 'data_mobil')}
        "))->map(fn ($row) => (array) $row);
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $requiredTables = ['data_mobil'];
        $existing = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$sourceDatabase]
        ))->pluck('TABLE_NAME')->all();

        $missing = array_values(array_diff($requiredTables, $existing));

        if ($missing !== []) {
            throw new InvalidArgumentException('Tabel legacy tidak lengkap: '.implode(', ', $missing));
        }
    }

    private function validateDatabaseName(string $sourceDatabase): string
    {
        $sourceDatabase = trim($sourceDatabase);

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $sourceDatabase)) {
            throw new InvalidArgumentException('Nama database legacy hanya boleh berisi huruf, angka, underscore, dan dash.');
        }

        return $sourceDatabase;
    }

    private function plate(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));

        if (! $this->filled($value)) {
            return null;
        }

        return mb_substr($value, 0, 20);
    }

    private function year(mixed $value): int
    {
        $year = (int) preg_replace('/\D+/', '', (string) $value);

        if ($year < 1901 || $year > 2155) {
            return 2000;
        }

        return $year;
    }

    private function money(mixed $value): int
    {
        return max(0, (int) round((float) $value));
    }

    private function text(?string $value, string $fallback, int $limit): string
    {
        $value = trim((string) $value);

        if (! $this->filled($value)) {
            $value = $fallback;
        }

        return mb_substr($value, 0, $limit);
    }

    private function filled(mixed $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '-' && $value !== '0';
    }

    private function dateOrNow(?string $date): Carbon
    {
        $date = trim((string) $date);

        if ($date === '' || str_starts_with($date, '0000-00-00') || str_starts_with($date, '-')) {
            return now();
        }

        try {
            $parsed = Carbon::parse($date);

            return $parsed->year < 1900 ? now() : $parsed;
        } catch (\Throwable) {
            return now();
        }
    }

    private function table(string $database, string $table): string
    {
        return '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
    }
}
