<?php

namespace App\Console\Commands;

use App\Services\LegacyRentToRentPaymentImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyImportRentToRentPaymentsCommand extends Command
{
    protected $signature = 'drent:legacy-import-rent-to-rent-payments
        {source_database : Nama database legacy MySQL, contoh: bandung2_r3nt4l}
        {--user-id= : User pencatat import. Default memakai user superadmin pertama atau user pertama}
        {--dry-run : Hitung import tanpa insert/update}';

    protected $description = 'Import pembayaran rent-to-rent / bagi hasil legacy sebagai pembayaran langsung tanpa dokumen tagihan lama.';

    public function handle(LegacyRentToRentPaymentImportService $service): int
    {
        $sourceDatabase = (string) $this->argument('source_database');
        $userId = $this->userId();

        if (! $userId) {
            $this->error('User pencatat tidak ditemukan. Isi --user-id jika perlu.');

            return self::FAILURE;
        }

        $this->info('Import pembayaran rent-to-rent / bagi hasil legacy DRENT');
        $this->line('Source database: '.$sourceDatabase);
        $this->line('Created by user: '.$userId);
        $this->line('Mode: '.($this->option('dry-run') ? 'dry-run' : 'apply import'));
        $this->newLine();

        try {
            $result = $service->import($sourceDatabase, $userId, (bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], collect($result)->map(
            fn ($value, $metric) => [$metric, $value]
        )->values()->all());

        return self::SUCCESS;
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
