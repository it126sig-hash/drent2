<?php

use App\Models\Invoice;
use App\Services\ReceivableService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('items_snapshot')->nullable()->after('paid_amount');
        });

        // Freeze existing invoices at their current line items.
        $service = app(ReceivableService::class);
        Invoice::with('bookings')->chunkById(100, function ($invoices) use ($service) {
            foreach ($invoices as $invoice) {
                $invoice->update(['items_snapshot' => $service->invoiceItems($invoice)->all()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('items_snapshot');
        });
    }
};
