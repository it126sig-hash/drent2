<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('modal_all_in')->default(0)->after('modal_1_bulan');
            $table->unsignedBigInteger('modal_all_in_1_minggu')->default(0)->after('modal_all_in');
            $table->unsignedBigInteger('modal_all_in_1_bulan')->default(0)->after('modal_all_in_1_minggu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['modal_all_in', 'modal_all_in_1_minggu', 'modal_all_in_1_bulan']);
        });
    }
};
