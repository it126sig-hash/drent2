<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyCustomerImportService
{
    public function import(string $sourceDatabase, int $tenantId, bool $dryRun = false): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $this->assertRequiredTablesExist($sourceDatabase);

        return DB::transaction(function () use ($sourceDatabase, $tenantId, $dryRun) {
            $owners = $this->loadOwners($sourceDatabase);
            $customers = $this->loadCustomers($sourceDatabase)->keyBy('kode_pelanggan');
            $ownerResult = $this->importOwners($sourceDatabase, $tenantId, $owners, $dryRun);
            $customerResult = $this->importCustomers($sourceDatabase, $tenantId, $customers, $dryRun);

            return [
                'owners' => $ownerResult,
                'customers' => $customerResult,
            ];
        });
    }

    private function importOwners(string $sourceDatabase, int $tenantId, Collection $owners, bool $dryRun): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'linked_existing' => 0,
            'skipped' => 0,
        ];

        foreach ($owners as $owner) {
            $payload = $this->ownerPayload($tenantId, $owner);
            $map = $this->findMap($sourceDatabase, 'data_pemilik', $owner['kode_pemilik']);
            $targetId = $map?->target_id;
            $existing = $targetId ? DB::table('rental_owners')->where('id', $targetId)->first() : null;
            $decision = 'import_owner';

            if (! $existing) {
                $existing = $this->findExistingOwner($tenantId, $payload);
                if ($existing) {
                    $targetId = $existing->id;
                    $decision = 'link_existing_owner';
                }
            }

            if ($dryRun) {
                $result[$existing ? ($decision === 'link_existing_owner' ? 'linked_existing' : 'updated') : 'created']++;
                continue;
            }

            if ($existing && $decision !== 'link_existing_owner') {
                DB::table('rental_owners')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
                $result['updated']++;
            } elseif ($existing) {
                $result['linked_existing']++;
            } else {
                $targetId = DB::table('rental_owners')->insertGetId($payload + [
                    'created_at' => $this->dateOrNow($owner['date_add'] ?? null),
                    'updated_at' => $this->dateOrNow($owner['date_mod'] ?? null),
                ]);
                $result['created']++;
            }

            $this->upsertMap($sourceDatabase, 'data_pemilik', $owner['kode_pemilik'], [
                'target_table' => 'rental_owners',
                'target_id' => $targetId,
                'decision' => $decision,
                'confidence_score' => 100,
                'metadata' => [
                    'nama_pemilik' => $owner['nama_pemilik'],
                    'phone' => $payload['kontak_1'],
                    'source' => 'legacy_customer_import',
                ],
            ]);
        }

        return $result;
    }

    private function importCustomers(string $sourceDatabase, int $tenantId, Collection $customers, bool $dryRun): array
    {
        $maps = DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', 'data_pelanggan')
            ->get()
            ->keyBy('legacy_id');

        $safeDecisions = ['import_customer', 'import_customer_and_link_owner'];
        $result = [
            'created' => 0,
            'updated' => 0,
            'linked_existing' => 0,
            'merged_to_canonical' => 0,
            'skipped_manual_review' => 0,
            'skipped_owner_only' => 0,
            'skipped_missing_source' => 0,
        ];

        foreach ($maps as $map) {
            if (! in_array($map->decision, $safeDecisions, true)) {
                if ($map->decision === 'manual_review') {
                    $result['skipped_manual_review']++;
                } elseif ($map->decision === 'skip_customer_import_owner_only') {
                    $result['skipped_owner_only']++;
                }

                continue;
            }

            $customer = $customers->get($map->legacy_id);
            if (! $customer) {
                $result['skipped_missing_source']++;
                continue;
            }

            $payload = $this->customerPayload($tenantId, $customer, $map);
            $targetId = $map->target_id;
            $existing = $targetId ? DB::table('customers')->where('id', $targetId)->first() : null;
            $decision = $map->decision;

            if (! $existing) {
                $existing = $this->findExistingCustomer($tenantId, $payload);
                if ($existing) {
                    $targetId = $existing->id;
                    $decision = 'link_existing_customer';
                }
            }

            if ($dryRun) {
                $result[$existing ? ($decision === 'link_existing_customer' ? 'linked_existing' : 'updated') : 'created']++;
                continue;
            }

            if ($existing && $decision !== 'link_existing_customer') {
                DB::table('customers')->where('id', $targetId)->update($payload + ['updated_at' => now()]);
                $result['updated']++;
            } elseif ($existing) {
                $result['linked_existing']++;
            } else {
                $targetId = DB::table('customers')->insertGetId($payload + [
                    'created_at' => $this->dateOrNow($customer['date_add'] ?? null),
                    'updated_at' => $this->dateOrNow($customer['date_mod'] ?? null),
                ]);
                $result['created']++;
            }

            $this->updateMapTarget($map->id, $targetId, $decision);
        }

        if (! $dryRun) {
            $result['merged_to_canonical'] = $this->linkMergedCustomersToCanonical($sourceDatabase);
        } else {
            $result['merged_to_canonical'] = $maps->where('decision', 'merge_to_canonical_customer')->count();
        }

        return $result;
    }

    private function linkMergedCustomersToCanonical(string $sourceDatabase): int
    {
        $mergeMaps = DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', 'data_pelanggan')
            ->where('decision', 'merge_to_canonical_customer')
            ->whereNotNull('canonical_legacy_id')
            ->get();

        $updated = 0;

        foreach ($mergeMaps as $mergeMap) {
            $canonical = DB::table('legacy_migration_maps')
                ->where('source_database', $sourceDatabase)
                ->where('legacy_table', 'data_pelanggan')
                ->where('legacy_id', $mergeMap->canonical_legacy_id)
                ->first();

            if (! $canonical?->target_id) {
                continue;
            }

            DB::table('legacy_migration_maps')->where('id', $mergeMap->id)->update([
                'target_table' => 'customers',
                'target_id' => $canonical->target_id,
                'updated_at' => now(),
            ]);

            $updated++;
        }

        return $updated;
    }

    private function ownerPayload(int $tenantId, array $owner): array
    {
        $phones = $this->normalizedPhones([$owner['hp'] ?? null, $owner['telp'] ?? null]);

        return [
            'tenant_id' => $tenantId,
            'nama' => $this->text($owner['nama_pemilik'] ?? null, 'Pemilik '.$owner['kode_pemilik'], 150),
            'kontak_1' => $this->contact($phones[0] ?? ($owner['hp'] ?? $owner['telp'] ?? null)),
            'kontak_2' => isset($phones[1]) ? $this->contact($phones[1]) : null,
            'alamat' => $this->nullableText($owner['alamat_pemilik'] ?? null),
            'kota' => $this->nullableText($owner['kota'] ?? null, 100),
            'bank' => $this->nullableText($owner['bank'] ?? null, 100),
            'no_rek' => $this->nullableText($owner['no_rek'] ?? null, 50),
            'atas_nama' => $this->nullableText($owner['atas_nama'] ?? null, 150),
            'is_owner' => true,
        ];
    }

    private function customerPayload(int $tenantId, array $customer, object $map): array
    {
        $phones = $this->normalizedPhones([
            $customer['hp1_pelanggan'] ?? null,
            $customer['hp2_pelanggan'] ?? null,
            $customer['telp_pelanggan'] ?? null,
        ]);
        $catatanParts = ['Legacy: '.$customer['kode_pelanggan']];

        if ($map->decision === 'import_customer_and_link_owner') {
            $catatanParts[] = 'Terdeteksi juga sebagai pemilik/rental owner; cek legacy_migration_maps.';
        }

        if ($customer['no_identitas'] ?? null) {
            $catatanParts[] = 'Identitas '.$this->text($customer['tipe_identitas'] ?? 'ID', 'ID', 20).': '.$customer['no_identitas'];
        }

        return [
            'tenant_id' => $tenantId,
            'nama' => $this->text($customer['nama_pelanggan'] ?? null, 'Pelanggan '.$customer['kode_pelanggan'], 150),
            'kontak_1' => $this->contact($phones[0] ?? ($customer['hp1_pelanggan'] ?? $customer['hp2_pelanggan'] ?? $customer['telp_pelanggan'] ?? null)),
            'kontak_2' => isset($phones[1]) ? $this->contact($phones[1]) : null,
            'email' => $this->nullableText($customer['email_pelanggan'] ?? null, 255),
            'alamat' => $this->nullableText($customer['alamat_pelanggan'] ?? null),
            'kota' => null,
            'status' => $this->customerStatus($customer['status'] ?? null),
            'has_apply_member' => ! empty($customer['kode_member']),
            'catatan' => implode(' | ', $catatanParts),
        ];
    }

    private function findExistingOwner(int $tenantId, array $payload): ?object
    {
        return DB::table('rental_owners')
            ->where('tenant_id', $tenantId)
            ->where('nama', $payload['nama'])
            ->get()
            ->first(fn (object $owner) => $this->recordContactsMatch($owner, $payload));
    }

    private function findExistingCustomer(int $tenantId, array $payload): ?object
    {
        return DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->where('nama', $payload['nama'])
            ->get()
            ->first(fn (object $customer) => $this->recordContactsMatch($customer, $payload));
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

    private function findMap(string $sourceDatabase, string $legacyTable, string $legacyId): ?object
    {
        return DB::table('legacy_migration_maps')
            ->where('source_database', $sourceDatabase)
            ->where('legacy_table', $legacyTable)
            ->where('legacy_id', $legacyId)
            ->first();
    }

    private function updateMapTarget(int $id, int $targetId, string $decision): void
    {
        DB::table('legacy_migration_maps')->where('id', $id)->update([
            'target_table' => 'customers',
            'target_id' => $targetId,
            'decision' => $decision,
            'updated_at' => now(),
        ]);
    }

    private function upsertMap(string $sourceDatabase, string $legacyTable, string $legacyId, array $values): void
    {
        DB::table('legacy_migration_maps')->upsert([
            [
                'source_database' => $sourceDatabase,
                'legacy_table' => $legacyTable,
                'legacy_id' => $legacyId,
                'target_table' => $values['target_table'],
                'target_id' => $values['target_id'],
                'canonical_legacy_id' => null,
                'decision' => $values['decision'],
                'confidence_score' => $values['confidence_score'],
                'metadata' => json_encode($values['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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

    private function loadCustomers(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                kode_pelanggan,
                kode_pemilik,
                status,
                nama_pelanggan,
                alamat_pelanggan,
                email_pelanggan,
                telp_pelanggan,
                hp1_pelanggan,
                hp2_pelanggan,
                tipe_identitas,
                no_identitas,
                kode_member,
                date_add,
                date_mod
            FROM {$this->table($sourceDatabase, 'data_pelanggan')}
        "))->map(fn ($row) => (array) $row);
    }

    private function loadOwners(string $sourceDatabase): Collection
    {
        return collect(DB::select("
            SELECT
                kode_pemilik,
                nama_pemilik,
                alamat_pemilik,
                kota,
                telp,
                hp,
                bank,
                no_rek,
                atas_nama,
                date_add,
                date_mod
            FROM {$this->table($sourceDatabase, 'data_pemilik')}
        "))->map(fn ($row) => (array) $row);
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $requiredTables = ['data_pelanggan', 'data_pemilik'];
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

    private function customerStatus(?string $legacyStatus): string
    {
        return match ($legacyStatus) {
            'Black List' => 'Blacklist',
            'Member' => 'Member',
            'Rental' => 'Rent to Rent',
            default => 'Normal',
        };
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

        if ($value === '' || $value === '-') {
            return '-';
        }

        return mb_substr($value, 0, 20);
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
