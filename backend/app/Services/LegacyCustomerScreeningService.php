<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyCustomerScreeningService
{
    private const REPORT_DIR = 'legacy-migration/reports';

    public function screen(string $sourceDatabase, int $minScore = 80): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $minScore = max(1, min(100, $minScore));

        $this->assertRequiredTablesExist($sourceDatabase);

        $customers = $this->loadCustomers($sourceDatabase);
        $owners = $this->loadOwners($sourceDatabase);
        $transactionCounts = $this->loadCustomerTransactionCounts($sourceDatabase);
        $ownerUnitCounts = $this->loadOwnerUnitCounts($sourceDatabase);

        $customers = $customers->map(fn (array $customer) => $this->enrichCustomer($customer, $transactionCounts));
        $owners = $owners->map(fn (array $owner) => $this->enrichOwner($owner, $ownerUnitCounts));

        $ownerCandidates = $this->buildOwnerCandidates($customers, $owners, $minScore);
        $duplicateCandidates = $this->buildDuplicateCandidates($customers, $minScore);
        $manualReviews = $this->buildManualReviews($ownerCandidates, $duplicateCandidates);
        $cleanImportPreview = $this->buildCleanImportPreview($customers, $ownerCandidates, $duplicateCandidates);

        return [
            'summary' => $this->buildSummary($sourceDatabase, $customers, $owners, $ownerCandidates, $duplicateCandidates, $manualReviews),
            'reports' => [
                'customer_duplicate_candidates' => $duplicateCandidates,
                'customer_owner_candidates' => $ownerCandidates,
                'customer_clean_import_preview' => $cleanImportPreview,
                'customer_manual_review' => $manualReviews,
            ],
        ];
    }

    public function exportReports(string $sourceDatabase, array $reports): array
    {
        $safeSource = preg_replace('/[^A-Za-z0-9_-]+/', '_', $sourceDatabase);
        $timestamp = now()->format('Ymd_His');
        $directory = storage_path('app/'.self::REPORT_DIR.'/'.$safeSource.'_'.$timestamp);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $paths = [];

        foreach ($reports as $name => $rows) {
            $path = $directory.'/'.$name.'.csv';
            $this->writeCsv($path, $rows);
            $paths[$name] = $path;
        }

        return $paths;
    }

    public function applyMappings(string $sourceDatabase, array $reports): array
    {
        $sourceDatabase = $this->validateDatabaseName($sourceDatabase);
        $now = now();
        $rows = $this->mappingRows($sourceDatabase, $reports, $now);

        if ($rows === []) {
            return [];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_migration_maps')->upsert(
                $chunk,
                ['source_database', 'legacy_table', 'legacy_id'],
                ['target_table', 'target_id', 'canonical_legacy_id', 'decision', 'confidence_score', 'metadata', 'updated_at']
            );
        }

        return collect($rows)
            ->groupBy('decision')
            ->map(fn (Collection $group) => $group->count())
            ->sortKeys()
            ->all();
    }

    private function validateDatabaseName(string $sourceDatabase): string
    {
        $sourceDatabase = trim($sourceDatabase);

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $sourceDatabase)) {
            throw new InvalidArgumentException('Nama database legacy hanya boleh berisi huruf, angka, underscore, dan dash.');
        }

        return $sourceDatabase;
    }

    private function assertRequiredTablesExist(string $sourceDatabase): void
    {
        $requiredTables = ['data_pelanggan', 'data_pemilik', 'booking', 'transaksi', 'data_mobil'];
        $existing = collect(DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$sourceDatabase]
        ))->pluck('TABLE_NAME')->all();

        $missing = array_values(array_diff($requiredTables, $existing));

        if ($missing !== []) {
            throw new InvalidArgumentException('Tabel legacy tidak lengkap: '.implode(', ', $missing));
        }
    }

    private function loadCustomers(string $sourceDatabase): Collection
    {
        return collect(DB::select($this->sourceSql($sourceDatabase, 'data_pelanggan', "
            SELECT
                kode_pelanggan,
                kode_pemilik,
                status,
                nama_pelanggan,
                tanggal_lahir,
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
            FROM %s
        ")))->map(fn ($row) => (array) $row);
    }

    private function loadOwners(string $sourceDatabase): Collection
    {
        return collect(DB::select($this->sourceSql($sourceDatabase, 'data_pemilik', "
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
            FROM %s
        ")))->map(fn ($row) => (array) $row);
    }

    private function loadCustomerTransactionCounts(string $sourceDatabase): array
    {
        $rows = DB::select("
            SELECT kode_pelanggan, SUM(total) AS total
            FROM (
                SELECT kode_pelanggan, COUNT(*) AS total
                FROM {$this->table($sourceDatabase, 'booking')}
                WHERE kode_pelanggan IS NOT NULL AND TRIM(kode_pelanggan) <> ''
                GROUP BY kode_pelanggan
                UNION ALL
                SELECT kode_pelanggan, COUNT(*) AS total
                FROM {$this->table($sourceDatabase, 'transaksi')}
                WHERE kode_pelanggan IS NOT NULL AND TRIM(kode_pelanggan) <> ''
                GROUP BY kode_pelanggan
            ) legacy_transactions
            GROUP BY kode_pelanggan
        ");

        return collect($rows)->mapWithKeys(fn ($row) => [$row->kode_pelanggan => (int) $row->total])->all();
    }

    private function loadOwnerUnitCounts(string $sourceDatabase): array
    {
        $rows = DB::select("
            SELECT kode_pemilik, COUNT(*) AS total
            FROM {$this->table($sourceDatabase, 'data_mobil')}
            WHERE kode_pemilik IS NOT NULL AND TRIM(kode_pemilik) <> ''
            GROUP BY kode_pemilik
        ");

        return collect($rows)->mapWithKeys(fn ($row) => [$row->kode_pemilik => (int) $row->total])->all();
    }

    private function enrichCustomer(array $customer, array $transactionCounts): array
    {
        $phones = $this->normalizePhones([
            $customer['hp1_pelanggan'] ?? null,
            $customer['hp2_pelanggan'] ?? null,
            $customer['telp_pelanggan'] ?? null,
        ]);

        $customer['normalized_name'] = $this->normalizeName($customer['nama_pelanggan'] ?? null);
        $customer['name_tokens'] = $this->nameTokens($customer['nama_pelanggan'] ?? null);
        $customer['normalized_phones'] = $phones;
        $customer['primary_phone'] = $phones[0] ?? null;
        $customer['normalized_identity'] = $this->normalizeIdentity($customer['no_identitas'] ?? null);
        $customer['transaction_count'] = $transactionCounts[$customer['kode_pelanggan']] ?? 0;
        $customer['completeness_score'] = $this->completenessScore($customer, [
            'nama_pelanggan',
            'alamat_pelanggan',
            'email_pelanggan',
            'hp1_pelanggan',
            'hp2_pelanggan',
            'telp_pelanggan',
            'no_identitas',
            'kode_member',
        ]);

        return $customer;
    }

    private function enrichOwner(array $owner, array $ownerUnitCounts): array
    {
        $phones = $this->normalizePhones([
            $owner['hp'] ?? null,
            $owner['telp'] ?? null,
        ]);

        $owner['normalized_name'] = $this->normalizeName($owner['nama_pemilik'] ?? null);
        $owner['name_tokens'] = $this->nameTokens($owner['nama_pemilik'] ?? null);
        $owner['normalized_phones'] = $phones;
        $owner['primary_phone'] = $phones[0] ?? null;
        $owner['unit_count'] = $ownerUnitCounts[$owner['kode_pemilik']] ?? 0;

        return $owner;
    }

    private function buildOwnerCandidates(Collection $customers, Collection $owners, int $minScore): array
    {
        $ownersByCode = $owners->keyBy('kode_pemilik');
        $ownersByPhone = $this->indexByMany($owners, 'normalized_phones');
        $ownersByName = $owners->filter(fn (array $owner) => $owner['normalized_name'])
            ->groupBy('normalized_name');

        $rows = [];

        foreach ($customers as $customer) {
            $matches = [];

            $legacyOwnerCode = $this->blankToNull($customer['kode_pemilik'] ?? null);
            if ($legacyOwnerCode && $ownersByCode->has($legacyOwnerCode)) {
                $owner = $ownersByCode->get($legacyOwnerCode);
                $matches[$owner['kode_pemilik']] = $this->scoreOwnerCandidate($customer, $owner, ['kode_pemilik'], $minScore);
            }

            foreach ($customer['normalized_phones'] as $phone) {
                foreach ($ownersByPhone[$phone] ?? [] as $owner) {
                    $reasons = $this->reasonList($matches[$owner['kode_pemilik']]['match_reasons'] ?? []);
                    $reasons[] = 'phone';
                    $matches[$owner['kode_pemilik']] = $this->scoreOwnerCandidate($customer, $owner, $reasons, $minScore);
                }
            }

            if ($customer['normalized_name'] && isset($ownersByName[$customer['normalized_name']])) {
                foreach ($ownersByName[$customer['normalized_name']] as $owner) {
                    $reasons = $this->reasonList($matches[$owner['kode_pemilik']]['match_reasons'] ?? []);
                    $reasons[] = 'exact_name';
                    $matches[$owner['kode_pemilik']] = $this->scoreOwnerCandidate($customer, $owner, $reasons, $minScore);
                }
            }

            foreach ($matches as $match) {
                $rows[] = $match;
            }
        }

        usort($rows, fn (array $a, array $b) => [$b['score'], $a['kode_pelanggan']] <=> [$a['score'], $b['kode_pelanggan']]);

        return array_values($rows);
    }

    private function scoreOwnerCandidate(array $customer, array $owner, array $reasons, int $minScore): array
    {
        $reasons = array_values(array_unique($reasons));
        $nameSimilarity = $this->nameSimilarity($customer, $owner);
        $statusBoost = ($customer['status'] ?? null) === 'Rental' ? 5 : 0;
        $score = $statusBoost;
        $hasCodeMatch = in_array('kode_pemilik', $reasons, true);
        $hasPhoneMatch = in_array('phone', $reasons, true);
        $hasNameMatch = in_array('exact_name', $reasons, true) || $nameSimilarity >= 80;

        if ($hasCodeMatch && ($hasPhoneMatch || $hasNameMatch || ($customer['status'] ?? null) === 'Rental')) {
            $score = 100;
        } elseif ($hasCodeMatch) {
            $score = 78 + $statusBoost;
        } elseif ($hasPhoneMatch && $nameSimilarity >= 80) {
            $score = min(99, 65 + (int) round($nameSimilarity * 0.35) + $statusBoost);
        } elseif ($hasPhoneMatch) {
            $score = 65 + $statusBoost;
        } elseif (in_array('exact_name', $reasons, true)) {
            $score = 70 + $statusBoost;
        }

        $decision = 'manual_review_owner';

        if ($hasCodeMatch && ($hasPhoneMatch || $hasNameMatch || ($customer['status'] ?? null) === 'Rental')) {
            $decision = 'auto_owner';
        } elseif ($hasPhoneMatch && $nameSimilarity >= 80 && $score >= $minScore) {
            $decision = 'auto_owner';
        }

        return [
            'decision' => $decision,
            'score' => min(100, $score),
            'match_reasons' => implode('+', $reasons),
            'name_similarity' => $nameSimilarity,
            'kode_pelanggan' => $customer['kode_pelanggan'],
            'nama_pelanggan' => $customer['nama_pelanggan'],
            'status_pelanggan' => $customer['status'],
            'kode_pemilik_di_pelanggan' => $customer['kode_pemilik'],
            'customer_phone' => $customer['primary_phone'],
            'transaction_count' => $customer['transaction_count'],
            'owner_code' => $owner['kode_pemilik'],
            'owner_name' => $owner['nama_pemilik'],
            'owner_phone' => $owner['primary_phone'],
            'owner_unit_count' => $owner['unit_count'],
            'import_customer_decision' => $customer['transaction_count'] > 0 ? 'keep_customer_and_owner' : 'owner_only',
        ];
    }

    private function reasonList(array|string $reasons): array
    {
        if (is_array($reasons)) {
            return $reasons;
        }

        if ($reasons === '') {
            return [];
        }

        return explode('+', $reasons);
    }

    private function buildDuplicateCandidates(Collection $customers, int $minScore): array
    {
        $rows = [];

        $groups = [
            'identity' => $customers->filter(fn (array $customer) => $customer['normalized_identity'])
                ->groupBy('normalized_identity')
                ->filter(fn (Collection $group) => $group->count() > 1),
            'phone' => collect($this->groupsByPhones($customers))
                ->filter(fn (array $group) => count($group) > 1),
            'name_phone' => $customers->filter(fn (array $customer) => $customer['normalized_name'] && $customer['primary_phone'])
                ->groupBy(fn (array $customer) => $customer['normalized_name'].'|'.$customer['primary_phone'])
                ->filter(fn (Collection $group) => $group->count() > 1),
            'name_only' => $customers->filter(fn (array $customer) => $customer['normalized_name'] && ! $customer['primary_phone'] && ! $customer['normalized_identity'])
                ->groupBy('normalized_name')
                ->filter(fn (Collection $group) => $group->count() > 1),
        ];

        foreach ($groups as $reason => $reasonGroups) {
            foreach ($reasonGroups as $groupKey => $group) {
                $records = collect($group)->values();
                $canonical = $this->chooseCanonicalCustomer($records);

                foreach ($records as $customer) {
                    $nameSimilarity = $this->nameSimilarity($customer, $canonical);
                    $score = $this->duplicateScore($reason, $customer, $canonical, $nameSimilarity);
                    $decision = $this->duplicateDecision($reason, $score, $minScore);

                    $rows[] = [
                        'group_id' => $reason.':'.$groupKey,
                        'decision' => $customer['kode_pelanggan'] === $canonical['kode_pelanggan'] ? 'canonical' : $decision,
                        'score' => $customer['kode_pelanggan'] === $canonical['kode_pelanggan'] ? 100 : $score,
                        'match_reason' => $reason,
                        'name_similarity' => $nameSimilarity,
                        'kode_pelanggan' => $customer['kode_pelanggan'],
                        'nama_pelanggan' => $customer['nama_pelanggan'],
                        'phone' => $customer['primary_phone'],
                        'identity' => $customer['normalized_identity'],
                        'transaction_count' => $customer['transaction_count'],
                        'completeness_score' => $customer['completeness_score'],
                        'canonical_kode_pelanggan' => $canonical['kode_pelanggan'],
                        'canonical_nama_pelanggan' => $canonical['nama_pelanggan'],
                        'canonical_transaction_count' => $canonical['transaction_count'],
                    ];
                }
            }
        }

        return $this->uniqueRows($rows, ['group_id', 'kode_pelanggan', 'canonical_kode_pelanggan']);
    }

    private function groupsByPhones(Collection $customers): array
    {
        $groups = [];

        foreach ($customers as $customer) {
            foreach ($customer['normalized_phones'] as $phone) {
                $groups[$phone][] = $customer;
            }
        }

        return $groups;
    }

    private function chooseCanonicalCustomer(Collection $customers): array
    {
        return $customers->sort(function (array $a, array $b) {
            return [
                $b['transaction_count'],
                $b['completeness_score'],
                $this->timestampForSort($a['date_add'] ?? null),
                $a['kode_pelanggan'],
            ] <=> [
                $a['transaction_count'],
                $a['completeness_score'],
                $this->timestampForSort($b['date_add'] ?? null),
                $b['kode_pelanggan'],
            ];
        })->first();
    }

    private function duplicateScore(string $reason, array $customer, array $canonical, int $nameSimilarity): int
    {
        return match ($reason) {
            'identity' => 100,
            'name_phone' => max(90, $nameSimilarity),
            'phone' => $nameSimilarity >= 85 ? 90 : 65,
            'name_only' => $nameSimilarity >= 95 ? 75 : 60,
            default => 50,
        };
    }

    private function duplicateDecision(string $reason, int $score, int $minScore): string
    {
        if (in_array($reason, ['identity', 'name_phone'], true) && $score >= $minScore) {
            return 'auto_merge';
        }

        if ($reason === 'phone' && $score >= $minScore) {
            return 'auto_merge';
        }

        return 'manual_review_duplicate';
    }

    private function buildManualReviews(array $ownerCandidates, array $duplicateCandidates): array
    {
        $rows = [];

        foreach ($ownerCandidates as $candidate) {
            if ($candidate['decision'] === 'manual_review_owner') {
                $rows[] = [
                    'review_type' => 'owner_candidate',
                    'decision' => $candidate['decision'],
                    'score' => $candidate['score'],
                    'kode_pelanggan' => $candidate['kode_pelanggan'],
                    'nama_pelanggan' => $candidate['nama_pelanggan'],
                    'reason' => $candidate['match_reasons'],
                    'comparison_code' => $candidate['owner_code'],
                    'comparison_name' => $candidate['owner_name'],
                    'notes' => 'Cek apakah pelanggan ini sebenarnya rental_owner.',
                ];
            }
        }

        foreach ($duplicateCandidates as $candidate) {
            if ($candidate['decision'] === 'manual_review_duplicate') {
                $rows[] = [
                    'review_type' => 'duplicate_candidate',
                    'decision' => $candidate['decision'],
                    'score' => $candidate['score'],
                    'kode_pelanggan' => $candidate['kode_pelanggan'],
                    'nama_pelanggan' => $candidate['nama_pelanggan'],
                    'reason' => $candidate['match_reason'],
                    'comparison_code' => $candidate['canonical_kode_pelanggan'],
                    'comparison_name' => $candidate['canonical_nama_pelanggan'],
                    'notes' => 'Cek apakah perlu digabung ke canonical customer.',
                ];
            }
        }

        return $rows;
    }

    private function buildCleanImportPreview(Collection $customers, array $ownerCandidates, array $duplicateCandidates): array
    {
        $bestOwnerDecision = collect($ownerCandidates)
            ->sortByDesc('score')
            ->groupBy('kode_pelanggan')
            ->map(fn (Collection $rows) => $rows->first());

        $duplicateDecisions = collect($duplicateCandidates)
            ->filter(fn (array $row) => $row['decision'] !== 'canonical')
            ->groupBy('kode_pelanggan')
            ->map(function (Collection $rows) {
                return $rows->sortByDesc(fn (array $row) => $row['score'])->first();
            });

        return $customers->map(function (array $customer) use ($bestOwnerDecision, $duplicateDecisions) {
            $owner = $bestOwnerDecision->get($customer['kode_pelanggan']);
            $duplicate = $duplicateDecisions->get($customer['kode_pelanggan']);

            $decision = 'import_customer';
            $target = 'customers';
            $canonical = null;
            $score = 100;
            $reason = 'clean';

            if ($duplicate && $duplicate['decision'] === 'auto_merge') {
                $decision = 'merge_to_canonical_customer';
                $target = 'customers';
                $canonical = $duplicate['canonical_kode_pelanggan'];
                $score = $duplicate['score'];
                $reason = $duplicate['match_reason'];
            } elseif ($owner && $owner['decision'] === 'auto_owner' && $customer['transaction_count'] === 0) {
                $decision = 'skip_customer_import_owner_only';
                $target = 'rental_owners';
                $score = $owner['score'];
                $reason = $owner['match_reasons'];
            } elseif ($owner && $owner['decision'] === 'auto_owner') {
                $decision = 'import_customer_and_link_owner';
                $target = 'customers+rental_owners';
                $score = $owner['score'];
                $reason = $owner['match_reasons'];
            } elseif (($owner && $owner['decision'] === 'manual_review_owner') || ($duplicate && $duplicate['decision'] === 'manual_review_duplicate')) {
                $decision = 'manual_review';
                $target = 'pending';
                $score = max($owner['score'] ?? 0, $duplicate['score'] ?? 0);
                $reason = trim(($owner['match_reasons'] ?? '').' '.($duplicate['match_reason'] ?? ''));
            }

            return [
                'decision' => $decision,
                'target' => $target,
                'score' => $score,
                'reason' => $reason,
                'kode_pelanggan' => $customer['kode_pelanggan'],
                'canonical_kode_pelanggan' => $canonical,
                'nama_pelanggan' => $customer['nama_pelanggan'],
                'status' => $customer['status'],
                'phone' => $customer['primary_phone'],
                'identity' => $customer['normalized_identity'],
                'transaction_count' => $customer['transaction_count'],
                'kode_pemilik' => $customer['kode_pemilik'],
            ];
        })->values()->all();
    }

    private function buildSummary(
        string $sourceDatabase,
        Collection $customers,
        Collection $owners,
        array $ownerCandidates,
        array $duplicateCandidates,
        array $manualReviews
    ): array {
        $emptyOwnerCodeCount = $customers->filter(function (array $customer) {
            return ! $this->blankToNull($customer['kode_pemilik'] ?? null);
        })->count();

        $duplicatePhoneGroups = collect($this->groupsByPhones($customers))
            ->filter(fn (array $group) => count($group) > 1);
        $duplicatePrimaryPhoneGroups = $customers->filter(fn (array $customer) => $customer['primary_phone'])
            ->groupBy('primary_phone')
            ->filter(fn (Collection $group) => $group->count() > 1);

        return [
            'source_database' => $sourceDatabase,
            'total_pelanggan' => $customers->count(),
            'total_pemilik' => $owners->count(),
            'kode_pemilik_empty_or_invalid' => $emptyOwnerCodeCount,
            'pelanggan_without_valid_phone' => $customers->filter(fn (array $customer) => ! $customer['primary_phone'])->count(),
            'duplicate_primary_phone_groups' => $duplicatePrimaryPhoneGroups->count(),
            'duplicate_primary_phone_records' => $duplicatePrimaryPhoneGroups->sum(fn (Collection $group) => $group->count()),
            'duplicate_any_phone_groups' => $duplicatePhoneGroups->count(),
            'duplicate_any_phone_records' => $duplicatePhoneGroups->sum(fn (array $group) => count($group)),
            'owner_auto_candidates' => collect($ownerCandidates)->where('decision', 'auto_owner')->count(),
            'owner_manual_review_candidates' => collect($ownerCandidates)->where('decision', 'manual_review_owner')->count(),
            'duplicate_auto_merge_records' => collect($duplicateCandidates)->where('decision', 'auto_merge')->count(),
            'duplicate_manual_review_records' => collect($duplicateCandidates)->where('decision', 'manual_review_duplicate')->count(),
            'manual_review_total' => count($manualReviews),
        ];
    }

    private function mappingRows(string $sourceDatabase, array $reports, mixed $now): array
    {
        $previewRows = collect($reports['customer_clean_import_preview'] ?? [])
            ->mapWithKeys(fn (array $row) => [$row['kode_pelanggan'] => $row]);
        $duplicateRows = collect($reports['customer_duplicate_candidates'] ?? [])
            ->filter(fn (array $row) => $row['decision'] !== 'canonical')
            ->groupBy('kode_pelanggan')
            ->map(fn (Collection $rows) => $rows->sortByDesc('score')->first());
        $ownerRows = collect($reports['customer_owner_candidates'] ?? [])
            ->groupBy('kode_pelanggan')
            ->map(fn (Collection $rows) => $rows->sortByDesc('score')->first());

        return $previewRows->map(function (array $preview) use ($sourceDatabase, $duplicateRows, $ownerRows, $now) {
            $duplicate = $duplicateRows->get($preview['kode_pelanggan']);
            $owner = $ownerRows->get($preview['kode_pelanggan']);
            $metadata = [
                'target' => $preview['target'],
                'reason' => $preview['reason'],
                'nama_pelanggan' => $preview['nama_pelanggan'],
                'status' => $preview['status'],
                'phone' => $preview['phone'],
                'identity' => $preview['identity'],
                'transaction_count' => $preview['transaction_count'],
                'kode_pemilik' => $preview['kode_pemilik'],
                'duplicate' => $duplicate,
                'owner' => $owner,
            ];

            return [
                'source_database' => $sourceDatabase,
                'legacy_table' => 'data_pelanggan',
                'legacy_id' => $preview['kode_pelanggan'],
                'target_table' => $this->targetTableForDecision($preview['decision']),
                'target_id' => null,
                'canonical_legacy_id' => $preview['canonical_kode_pelanggan'] ?: null,
                'decision' => $preview['decision'],
                'confidence_score' => $preview['score'],
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();
    }

    private function targetTableForDecision(string $decision): ?string
    {
        return match ($decision) {
            'skip_customer_import_owner_only' => 'rental_owners',
            'import_customer_and_link_owner' => 'customers+rental_owners',
            'manual_review' => null,
            default => 'customers',
        };
    }

    private function normalizePhones(array $phones): array
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

    private function normalizeName(?string $name): ?string
    {
        $name = strtolower(trim((string) $name));

        if ($name === '' || $name === '-') {
            return null;
        }

        $name = preg_replace('/\b(pt|cv|rental|rent|car|mobil|transport|trans)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9]+/u', '', $name);

        return $name !== '' ? $name : null;
    }

    private function nameTokens(?string $name): array
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/\b(pt|cv|rental|rent|car|mobil|transport|trans)\b/u', ' ', $name);
        $parts = preg_split('/[^a-z0-9]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

        return collect($parts)
            ->filter(fn (string $token) => strlen($token) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeIdentity(?string $identity): ?string
    {
        $identity = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', trim((string) $identity)));

        if ($identity === '' || $identity === '-' || $identity === '0' || strlen($identity) < 6) {
            return null;
        }

        return $identity;
    }

    private function nameSimilarity(array $left, array $right): int
    {
        $leftName = $left['normalized_name'] ?? null;
        $rightName = $right['normalized_name'] ?? null;

        if (! $leftName || ! $rightName) {
            return 0;
        }

        if ($leftName === $rightName) {
            return 100;
        }

        similar_text($leftName, $rightName, $percent);

        $leftTokens = collect($left['name_tokens'] ?? []);
        $rightTokens = collect($right['name_tokens'] ?? []);
        $tokenScore = 0;

        if ($leftTokens->isNotEmpty() && $rightTokens->isNotEmpty()) {
            $intersection = $leftTokens->intersect($rightTokens)->count();
            $base = max($leftTokens->count(), $rightTokens->count());
            $tokenScore = (int) round(($intersection / $base) * 100);
        }

        return (int) round(max($percent, $tokenScore));
    }

    private function completenessScore(array $record, array $fields): int
    {
        return collect($fields)
            ->sum(fn (string $field) => $this->blankToNull($record[$field] ?? null) ? 1 : 0);
    }

    private function indexByMany(Collection $records, string $field): array
    {
        $index = [];

        foreach ($records as $record) {
            foreach ($record[$field] as $value) {
                $index[$value][] = $record;
            }
        }

        return $index;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-' || $value === '0') {
            return null;
        }

        return $value;
    }

    private function timestampForSort(?string $value): int
    {
        if (! $value) {
            return PHP_INT_MAX;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? PHP_INT_MAX : $timestamp;
    }

    private function uniqueRows(array $rows, array $keys): array
    {
        $seen = [];
        $unique = [];

        foreach ($rows as $row) {
            $identity = implode('|', array_map(fn (string $key) => (string) ($row[$key] ?? ''), $keys));

            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException('Tidak bisa menulis report: '.$path);
        }

        if ($rows === []) {
            fputcsv($handle, ['empty']);
            fclose($handle);

            return;
        }

        $headers = array_keys($rows[0]);
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header) => $row[$header] ?? null, $headers));
        }

        fclose($handle);
    }

    private function sourceSql(string $sourceDatabase, string $table, string $sql): string
    {
        return sprintf($sql, $this->table($sourceDatabase, $table));
    }

    private function table(string $database, string $table): string
    {
        return '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
    }
}
