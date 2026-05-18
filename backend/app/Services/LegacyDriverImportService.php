<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyDriverImportService
{
    public function import(string $sourceDatabase, int $tenantId, int $branchId, bool $dryRun = false): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $this->assertRequiredTablesExist($sourceDatabase);

        return DB::transaction(function () use ($sourceDatabase, $tenantId, $branchId, $dryRun) {
            $drivers = $this->loadDrivers($sourceDatabase);

            return $this->importDrivers($sourceDatabase, $tenantId, $branchId, $drivers, $dryRun);
        });
    }

    private function importDrivers(string $sourceDatabase, int $tenantId, int $branchId, Collection $drivers, bool $dryRun): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'linked_existing' => 0,
            'skipped_placeholder' => 0,
            'fallback_contact' => 0,
        ];

        foreach ($drivers as $driver) {
            if ($this->isNoDriverPlaceholder($driver)) {
                $result['skipped_placeholder']++;

                if (! $dryRun) {
                    $this->upsertSkipMap($sourceDatabase, $driver);
                }

                continue;
            }

            $payload = $this->driverPayload($tenantId, $branchId, $driver);
            $map = $this->findMap($sourceDatabase, 'data_supir', $driver['kode_supir']);
            $targetId = $map?->target_id;
            $existing = $targetId ? DB::table('drivers')->where('id', $targetId)->first() : null;
            $decision = 'import_driver';

            if ($payload['kontak_1'] === '-') {
                $result['fallback_contact']++;
            }

            if (! $existing) {
                $existing = $this->findExistingDriver($tenantId, $payload);

                if ($existing) {
                    $targetId = $existing->id;
                    $decision = 'link_existing_driver';
                }
            }

            if ($dryRun) {
                $result[$existing ? ($decision === 'link_existing_driver' ? 'linked_existing' : 'updated') : 'created']++;
                continue;
            }

            if ($existing && $decision !== 'link_existing_driver') {
                DB::table('drivers')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
                $result['updated']++;
            } elseif ($existing) {
                $result['linked_existing']++;
            } else {
                $targetId = DB::table('drivers')->insertGetId($payload + [
                    'created_at' => $this->dateOrNow($driver['date_add'] ?? null),
                    'updated_at' => $this->dateOrNow($driver['date_mod'] ?? null),
                ]);
                $result['created']++;
            }

            $this->upsertMap($sourceDatabase, $driver, $targetId, $decision, $payload);
        }

        return $result;
    }

    private function driverPayload(int $tenantId, int $branchId, array $driver): array
    {
        $phones = $this->normalizedPhones([
            $driver['hp1_supir'] ?? null,
            $driver['hp2_supir'] ?? null,
        ]);
        $catatan = ['Legacy: '.$driver['kode_supir']];

        if ($this->filled($driver['add_by'] ?? null)) {
            $catatan[] = 'Add by: '.$driver['add_by'];
        }

        if ($this->filled($driver['mod_by'] ?? null)) {
            $catatan[] = 'Mod by: '.$driver['mod_by'];
        }

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => null,
            'nama' => $this->text($driver['nama_supir'] ?? null, 'Driver '.$driver['kode_supir'], 150),
            'alamat' => $this->nullableText($driver['alamat_supir'] ?? null),
            'kota' => null,
            'no_sim' => $this->nullableText($driver['sim'] ?? null, 30),
            'kontak_1' => $this->contact($phones[0] ?? ($driver['hp1_supir'] ?? $driver['hp2_supir'] ?? null)),
            'kontak_2' => isset($phones[1]) ? $this->contact($phones[1]) : null,
            'saldo' => 0,
            'status' => 'Aktif',
            'is_tetap' => false,
            'catatan' => implode(' | ', $catatan),
        ];
    }

    private function findExistingDriver(int $tenantId, array $payload): ?object
    {
        return DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('nama', $payload['nama'])
            ->get()
            ->first(fn (object $driver) => $this->recordContactsMatch($driver, $payload));
    }

    private function recordContactsMatch(object $record, array $payload): bool
    {
        $recordPhones = $this->normalizedPhones([$record->kontak_1 ?? null, $record->kontak_2 ?? null]);
        $payloadPhones = $this->normalizedPhones([$payload['kontak_1'] ?? null, $payload['kontak_2'] ?? null]);

        if ($recordPhones !== [] && $payloadPhones !== []) {
            return collect($recordPhones)->intersect($payloadPhones)->isNotEmpty();
        }

        return ($record->kontak_1 ?? null) === $payload['kontak_1'];
    }

    private function upsertMap(string $sourceDatabase, array $driver, int $targetId, string $decision, array $payload): void
    {
        DB::table('legacy_migration_maps')->upsert([
            [
                'source_database' => $sourceDatabase,
                'legacy_table' => 'data_supir',
                'legacy_id' => $driver['kode_supir'],
                'target_table' => 'drivers',
                'target_id' => $targetId,
                'canonical_legacy_id' => null,
                'decision' => $decision,
                'confidence_score' => $decision === 'link_existing_driver' ? 90 : 100,
                'metadata' => json_encode([
                    'nama_supir' => $driver['nama_supir'],
                    'hp1_supir' => $driver['hp1_supir'],
                    'hp2_supir' => $driver['hp2_supir'],
                    'mapped_contact' => $payload['kontak_1'],
                    'source' => 'legacy_driver_import',
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

    private function upsertSkipMap(string $sourceDatabase, array $driver): void
    {
        DB::table('legacy_migration_maps')->upsert([
            [
                'source_database' => $sourceDatabase,
                'legacy_table' => 'data_supir',
                'legacy_id' => $driver['kode_supir'],
                'target_table' => 'drivers',
                'target_id' => null,
                'canonical_legacy_id' => null,
                'decision' => 'skip_no_driver_placeholder',
                'confidence_score' => 100,
                'metadata' => json_encode([
                    'nama_supir' => $driver['nama_supir'],
                    'source' => 'legacy_driver_import',
                    'note' => 'Placeholder legacy untuk booking tanpa driver.',
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

    private function findMap(string $sourceDatabase, string $legacyTable, string $legacyId): ?object
    {
        return DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->first();
    }

    private function loadDrivers(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                kode_supir,
                nama_supir,
                hp1_supir,
                hp2_supir,
                alamat_supir,
                sim,
                date_add,
                add_by,
                date_mod,
                mod_by
            FROM {$this->table($sourceDatabase, 'data_supir')}
        "))->map(fn ($row) => (array) $row);
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $existing = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$sourceDatabase]
        ))->pluck('TABLE_NAME')->all();

        if (! in_array('data_supir', $existing, true)) {
            throw new InvalidArgumentException('Tabel legacy tidak lengkap: data_supir');
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

    private function isNoDriverPlaceholder(array $driver): bool
    {
        $name = strtolower(trim((string) ($driver['nama_supir'] ?? '')));

        return str_contains($name, 'tidak pakai supir')
            || str_contains($name, 'tanpa supir')
            || str_contains($name, 'lepas kunci');
    }

    private function normalizedPhones(array $phones): array
    {
        return collect($phones)
            ->map(fn ($phone) => $this->normalizePhone($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === '0') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! preg_match('/^0[0-9]{8,}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function contact(?string $value): string
    {
        $value = $this->normalizePhone($value) ?? trim((string) $value);

        if (! $this->filled($value)) {
            return '-';
        }

        return mb_substr($value, 0, 20);
    }

    private function text(?string $value, string $fallback, int $limit): string
    {
        $value = trim((string) $value);

        if (! $this->filled($value)) {
            $value = $fallback;
        }

        return mb_substr($value, 0, $limit);
    }

    private function nullableText(?string $value, ?int $limit = null): ?string
    {
        $value = trim((string) $value);

        if (! $this->filled($value)) {
            return null;
        }

        return $limit ? mb_substr($value, 0, $limit) : $value;
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
