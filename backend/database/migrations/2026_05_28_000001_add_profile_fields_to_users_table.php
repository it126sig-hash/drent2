<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik')->nullable()->after('email');
            $table->text('alamat')->nullable()->after('nik');
            $table->string('no_rekening')->nullable()->after('alamat');
            $table->string('bank')->nullable()->after('no_rekening');
            $table->string('atas_nama')->nullable()->after('bank');
            $table->string('kontak')->nullable()->after('atas_nama');
            $table->string('foto_profile_path')->nullable()->after('kontak');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nik',
                'alamat',
                'no_rekening',
                'bank',
                'atas_nama',
                'kontak',
                'foto_profile_path',
            ]);
        });
    }
};
