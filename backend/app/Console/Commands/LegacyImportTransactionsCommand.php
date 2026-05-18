<?php

namespace App\Console\Commands;

use App\Services\LegacyTransactionImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyImportTransactionsCommand extends Command
{
    protected $signature = 'drent:legacy-import-transactions
        {source_database : Nama database legacy MySQL, contoh: bandung2_r3nt4l}
        {--tenant-id= : Tenant target. Default memakai tenant pertama}
        {--branch-id= : Branch target. Default memakai branch pertama}
        {--user-id= : User pencatat import. Default memakai user superadmin pertama atau user pertama}
        {--dry-run : Hitung import tanpa insert/update}';

    protected $description = 'Import transaksi rental legacy dan pembayaran customer tanpa membawa invoice lama.';

    public function handle(LegacyTransactionImportService $service): int
    {
        $sourceDatabase = (string) $this->argument('source_database');
        $tenantId = $this->tenantId();
        $branchId = $this->branchId($tenantId);
        $userId = $this->userId();

        if (! $tenantId || ! $branchId || ! $userId) {
            $this->error('Tenant, branch, atau user pencatat tidak ditemukan. Isi --tenant-id/--branch-id/--user-id jika perlu.');

            return self::FAILURE;
        }

        $this->info('Import transaksi rental legacy DRENT');
        $this->line('Source database: '.$sourceDatabase);
        $this->line('Tenant target: '.$tenantId);
        $this->line('Branch target: '.$branchId);
        $this->line('Created by user: '.$userId);
        $this->line('Mode: '.($this->option('dry-run') ? 'dry-run' : 'apply import'));
        $this->newLine();

        try {
            $result = $service->import($sourceDatabase, $tenantId, $branchId, $userId, (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], collect($result)->map(
            fn ($value, $metric) => [$metric, $value]
        )->values()->all());

        return self::SUCCESS;
    }

    private function tenantId(): ?int
    {
        $option = $this->option('tenant-id');

        if ($option) {
            return (int) $option;
        }

        return DB::table('tenants')->orderBy('id')->value('id');
    }

    private function branchId(?int $tenantId): ?int
    {
        $option = $this->option('branch-id');

        if ($option) {
            return (int) $option;
        }

        return DB::table('branches')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->value('id');
    }

    private function userId(): ?int
    {
        $option = $this->option('user-id');

        if ($option) {
            return (int) $option;
        }

        return DB::table('users')
            ->orderByRaw("CASE WHEN role = 'superadmin' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');
    }
}
