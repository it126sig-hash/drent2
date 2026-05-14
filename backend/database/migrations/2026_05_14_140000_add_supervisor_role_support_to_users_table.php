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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')
                ->default('operator')
                ->comment('superadmin, admin_branch, supervisor, finance, driver_tetap, cs, teknisi')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')
                ->default('operator')
                ->comment('superadmin, admin_branch, finance, operator, driver_tetap')
                ->change();
        });
    }
};
