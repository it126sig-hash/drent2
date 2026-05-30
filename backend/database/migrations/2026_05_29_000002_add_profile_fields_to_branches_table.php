<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('phone_alt', 30)->nullable()->after('phone');
            $table->string('email')->nullable()->after('phone_alt');
            $table->string('website')->nullable()->after('email');
            $table->string('instagram')->nullable()->after('website');
            $table->string('tiktok')->nullable()->after('instagram');
            $table->string('facebook')->nullable()->after('tiktok');
            $table->string('logo_path')->nullable()->after('facebook');
            $table->foreignId('city_id')
                ->nullable()
                ->after('logo_path')
                ->constrained('cities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn([
                'phone_alt',
                'email',
                'website',
                'instagram',
                'tiktok',
                'facebook',
                'logo_path',
                'city_id',
            ]);
        });
    }
};
