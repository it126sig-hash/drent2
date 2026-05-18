<?php

namespace App\Console\Commands;

use App\Services\LegacyCustomerScreeningService;
use Illuminate\Console\Command;
use Throwable;

class LegacyScreenCustomersCommand extends Command
{
    protected $signature = 'drent:legacy-screen-customers
        {source_database : Nama database legacy MySQL, contoh: bandung2_r3nt4l}
        {--export : Export CSV report ke storage/app/legacy-migration/reports}
        {--dry-run : Jalankan screening read-only tanpa efek migrasi}
        {--apply : Simpan hasil screening ke tabel legacy_migration_maps}
        {--min-score=80 : Minimum score untuk keputusan otomatis}';

    protected $description = 'Screening pelanggan legacy untuk duplikat dan pelanggan yang sebenarnya pemilik/rental owner.';

    public function handle(LegacyCustomerScreeningService $service): int
    {
        $sourceDatabase = (string) $this->argument('source_database');
        $minScore = (int) $this->option('min-score');

        $this->info('Screening pelanggan legacy DRENT');
        $this->line('Source database: '.$sourceDatabase);
        $this->line('Mode: '.$this->modeLabel());
        $this->line('Minimum auto score: '.$minScore);
        $this->newLine();

        try {
            $result = $service->screen($sourceDatabase, $minScore);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], collect($result['summary'])->map(
            fn ($value, $metric) => [$metric, $value]
        )->values()->all());

        $this->newLine();
        $this->line('Report rows:');
        foreach ($result['reports'] as $name => $rows) {
            $this->line('- '.$name.': '.count($rows));
        }

        if ($this->option('export')) {
            try {
                $paths = $service->exportReports($sourceDatabase, $result['reports']);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('CSV report dibuat:');
            foreach ($paths as $name => $path) {
                $this->line('- '.$name.': '.$path);
            }
        } else {
            $this->newLine();
            $this->comment('Tambahkan --export untuk membuat CSV report.');
        }

        if ($this->option('apply')) {
            try {
                $applied = $service->applyMappings($sourceDatabase, $result['reports']);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('Mapping screening disimpan ke database:');
            foreach ($applied as $decision => $count) {
                $this->line('- '.$decision.': '.$count);
            }
        } elseif (! $this->option('dry-run')) {
            $this->newLine();
            $this->comment('Tambahkan --apply untuk menyimpan hasil screening ke legacy_migration_maps.');
        }

        return self::SUCCESS;
    }

    private function modeLabel(): string
    {
        if ($this->option('apply')) {
            return $this->option('dry-run') ? 'dry-run + apply requested' : 'apply mapping';
        }

        return $this->option('dry-run') ? 'dry-run' : 'read-only screening';
    }
}
