<?php

namespace App\Console\Commands;

use App\Models\RentToRentDebt;
use App\Services\RentToRentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rent-to-rent:sync-cache')]
#[Description('Sync cached total, paid amounts and payment status for rent-to-rent debts.')]
class RentToRentSyncCacheCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RentToRentService $service)
    {
        $this->info('Sync missing rent-to-rent debts...');
        $service->syncMissingDebts();

        $this->info('Sync cached rent-to-rent amounts and statuses...');

        $count = RentToRentDebt::count();
        $bar = $this->output->createProgressBar($count);

        RentToRentDebt::query()
            ->with([
                'bookingDetail.unit',
                'billItems.bill',
                'billItems.allocations.payment',
                'paymentAllocations.payment',
            ])
            ->chunkById(100, function ($debts) use ($service, $bar) {
                foreach ($debts as $debt) {
                    $service->refreshDebtCache($debt);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Cache sync completed successfully!');
    }
}
