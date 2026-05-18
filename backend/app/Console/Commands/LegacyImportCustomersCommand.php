<?php

namespace App\Console\Commands;

use App\Services\LegacyCustomerImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyImportCustomersCommand extends Command
{
    protected $signature = 'drent:legacy-import-customers
        {source_database : Nama database legacy MySQL, contoh: bandung2_r3nt4l}
        {--tenant-id= : Tenant target. Default memakai tenant pertama}
        {--dry-run : Hitung import tanpa insert/update}';

    protected $description = 'Import master pemilik dan pelanggan legacy memakai hasil screening legacy_migration_maps.';

    public function handle(LegacyCustomerImportService $service): int
    {
        $sourceDatabase = (string) $this->argument('source_database');
        $tenantId = $this->tenantId();

        if (! $tenantId) {
            $this->error('Tenant target tidak ditemukan. Buat tenant dulu atau isi --tenant-id.');

            return self::FAILURE;
        }

        $this->info('Import master pelanggan legacy DRENT');
        $this->line('Source database: '.$sourceDatabase);
        $this->line('Tenant target: '.$tenantId);
        $this->line('Mode: '.($this->option('dry-run') ? 'dry-run' : 'apply import'));
        $this->newLine();

        try {
            $result = $service->import($sourceDatabase, $tenantId, (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result as $section => $counts) {
            $this->info(ucfirst($section));
            $this->table(['Metric', 'Value'], collect($counts)->map(
                fn ($value, $metric) => [$metric, $value]
            )->values()->all());
        }

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
}
