<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('merk', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('units')->whereNull('merk')->update(['merk' => '']);

        Schema::table('units', function (Blueprint $table) {
            $table->string('merk', 100)->nullable(false)->change();
        });
    }
};
