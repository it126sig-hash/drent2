<?php

use App\Models\Booking;
use App\Services\BookingBillingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('cached_sisa_tagihan')->default(0)->after('due_date');

            // Index gabungan untuk query ReceivableService::list()
            // Mendukung: WHERE tenant_id=? AND branch_id=? AND status NOT IN ('batal')
            //            AND cached_sisa_tagihan > 0 AND deleted_at IS NULL ORDER BY created_at DESC
            $table->index(
                ['tenant_id', 'branch_id', 'status', 'cached_sisa_tagihan'],
                'bookings_receivable_query_idx'
            );
        });

        // Populate existing data via raw SQL — jauh lebih cepat daripada per-model PHP
        // Formula sama dengan SyncReceivableCache command & BookingBillingService
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE bookings b
            SET cached_sisa_tagihan = GREATEST(0,
                COALESCE((
                    SELECT SUM(
                        CASE
                            WHEN bd.pricing_mode = 'all_in' THEN
                                (COALESCE(bd.harga_all_in, 0) * COALESCE(bd.lama_sewa, 1))
                                + COALESCE((
                                    SELECT SUM(CASE WHEN bc.type = 'diskon' THEN -(bc.amount)
                                                    WHEN bc.is_additional = 1 AND bc.type != 'diskon' THEN bc.amount
                                                    ELSE 0 END)
                                    FROM booking_costs bc WHERE bc.booking_detail_id = bd.id
                                ), 0)
                            ELSE
                                ((COALESCE(bd.harga_mobil, 0) - COALESCE(bd.diskon_mobil, 0)) * COALESCE(bd.lama_sewa, 1))
                                + COALESCE((
                                    SELECT SUM(CASE WHEN bc.type = 'diskon' THEN -(bc.amount) ELSE bc.amount END)
                                    FROM booking_costs bc WHERE bc.booking_detail_id = bd.id
                                ), 0)
                        END
                    )
                    FROM booking_details bd
                    WHERE bd.booking_id = b.id AND bd.status NOT IN ('batal') AND bd.deleted_at IS NULL
                ), 0)
                -
                COALESCE((
                    SELECT SUM(bp.amount) FROM booking_payments bp
                    WHERE bp.booking_id = b.id AND COALESCE(bp.status, 'active') != 'voided'
                ), 0)
            )
            WHERE b.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_receivable_query_idx');
            $table->dropColumn('cached_sisa_tagihan');
        });
    }
};
