<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_to_rent_debts', function (Blueprint $table) {
            if (! $this->indexExists('rent_to_rent_debts', 'rtr_debts_list_query_idx')) {
                $table->index([
                    'tenant_id',
                    'branch_id',
                    'status',
                    'cached_payment_status',
                    'rental_owner_id',
                    'deleted_at',
                    'created_at',
                ], 'rtr_debts_list_query_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rent_to_rent_debts', function (Blueprint $table) {
            if ($this->indexExists('rent_to_rent_debts', 'rtr_debts_list_query_idx')) {
                $table->dropIndex('rtr_debts_list_query_idx');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
    }
};
