<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('kode_booking');
        });

        DB::table('bookings')->whereNull('public_token')->orderBy('id')->select('id')
            ->chunkById(200, function ($bookings) {
                foreach ($bookings as $booking) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('bookings')->where('public_token', $token)->exists());

                    DB::table('bookings')->where('id', $booking->id)->update(['public_token' => $token]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
