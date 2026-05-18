<?php

namespace App\Console\Commands;

use App\Services\LegacyUnitImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyImportUnitsCommand extends Command
{
    protected $signature = 'drent:legacy-import-units
        {source_database : Nama database legacy MySQL, contoh: bandung2_r3nt4l}
        {--tenant-id= : Tenant target. Default memakai tenant pertama}
        {--branch-id= : Branch target. Default memakai branch pertama}
        {--dry-run : Hitung import tanpa insert/update}';

    protected $description = 'Import data mobil legacy ke units memakai mapping rental owner.';

    public function handle(LegacyUnitImportService $service): int
    {
        $sourceDatabase = (string) $this->argument('source_database');
        $tenantId = $this->tenantId();
        $branchId = $this->branchId($tenantId);

        if (! $tenantId || ! $branchId) {
            $this->error('Tenant/branch target tidak ditemukan. Buat tenant dan branch dulu atau isi --tenant-id/--branch-id.');

            return self::FAILURE;
        }

        $this->info('Import data mobil legacy DRENT');
        $this->line('Source database: '.$sourceDatabase);
        $this->line('Tenant target: '.$tenantId);
        $this->line('Branch target: '.$branchId);
        $this->line('Mode: '.($this->option('dry-run') ? 'dry-run' : 'apply import'));
        $this->newLine();

        try {
            $result = $service->import($sourceDatabase, $tenantId, $branchId, (bool) $this->option('dry-run'));
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
}
