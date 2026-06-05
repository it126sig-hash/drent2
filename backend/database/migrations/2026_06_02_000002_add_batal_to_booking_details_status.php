<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE booking_details MODIFY COLUMN status ENUM('draft', 'aktif', 'selesai', 'batal') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE booking_details MODIFY COLUMN status ENUM('draft', 'aktif', 'selesai') NOT NULL DEFAULT 'draft'");
    }
};
