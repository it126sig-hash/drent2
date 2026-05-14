<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_checks', function (Blueprint $table) {
            $table->string('public_token', 80)->nullable()->unique()->after('status');
            $table->dateTime('public_last_opened_at')->nullable()->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('physical_checks', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'public_last_opened_at']);
        });
    }
};
