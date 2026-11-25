<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('follow_up', 'confirm', 'waiting_list', 'rental_unit', 'selesai', 'batal', 'reschedule') NOT NULL DEFAULT 'follow_up'");
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('reschedule_date')->nullable()->after('catatan_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reschedule_date');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('follow_up', 'confirm', 'waiting_list', 'rental_unit', 'selesai', 'batal') NOT NULL DEFAULT 'follow_up'");
        }
    }
};
