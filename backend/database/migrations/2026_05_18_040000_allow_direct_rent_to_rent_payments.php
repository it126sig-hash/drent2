<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $usesSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! $usesSqlite) {
            Schema::table('rent_to_rent_payments', function (Blueprint $table) {
                $table->dropForeign('rtr_payments_bill_fk');
            });

            Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
                $table->dropForeign('rtr_alloc_item_fk');
            });
        }

        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('rent_to_rent_bill_id')->nullable()->change();
        });

        Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('rent_to_rent_bill_item_id')->nullable()->change();
        });

        if ($usesSqlite) {
            return;
        }

        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->foreign('rent_to_rent_bill_id', 'rtr_payments_bill_fk')
                ->references('id')
                ->on('rent_to_rent_bills')
                ->nullOnDelete();
        });

        Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
            $table->foreign('rent_to_rent_bill_item_id', 'rtr_alloc_item_fk')
                ->references('id')
                ->on('rent_to_rent_bill_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $usesSqlite = DB::connection()->getDriverName() === 'sqlite';

        DB::table('rent_to_rent_payment_allocations')
            ->whereNull('rent_to_rent_bill_item_id')
            ->delete();

        DB::table('rent_to_rent_payments')
            ->whereNull('rent_to_rent_bill_id')
            ->delete();

        if (! $usesSqlite) {
            Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
                $table->dropForeign('rtr_alloc_item_fk');
            });

            Schema::table('rent_to_rent_payments', function (Blueprint $table) {
                $table->dropForeign('rtr_payments_bill_fk');
            });
        }

        Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('rent_to_rent_bill_item_id')->nullable(false)->change();
        });

        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('rent_to_rent_bill_id')->nullable(false)->change();
        });

        if ($usesSqlite) {
            return;
        }

        Schema::table('rent_to_rent_payment_allocations', function (Blueprint $table) {
            $table->foreign('rent_to_rent_bill_item_id', 'rtr_alloc_item_fk')
                ->references('id')
                ->on('rent_to_rent_bill_items')
                ->cascadeOnDelete();
        });

        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->foreign('rent_to_rent_bill_id', 'rtr_payments_bill_fk')
                ->references('id')
                ->on('rent_to_rent_bills')
                ->cascadeOnDelete();
        });
    }
};
