<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE booking_details MODIFY COLUMN status ENUM('draft', 'aktif', 'selesai', 'batal') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE booking_details MODIFY COLUMN status ENUM('draft', 'aktif', 'selesai') NOT NULL DEFAULT 'draft'");
    }
};
