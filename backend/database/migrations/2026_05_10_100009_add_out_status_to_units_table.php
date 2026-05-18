<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE units MODIFY COLUMN status ENUM('Aktif', 'Tidak Aktif', 'Dalam Servis', 'Out') DEFAULT 'Aktif'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE units MODIFY COLUMN status ENUM('Aktif', 'Tidak Aktif', 'Dalam Servis') DEFAULT 'Aktif'");
    }
};
