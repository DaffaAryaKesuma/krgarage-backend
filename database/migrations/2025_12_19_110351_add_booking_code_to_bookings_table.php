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
        if (!Schema::hasColumn('bookings', 'kode_pemesanan')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('kode_pemesanan', 20)->unique()->after('id')->nullable();
            });

            // Generate booking code untuk data yang sudah ada
            $bookings = \App\Models\Booking::whereNull('kode_pemesanan')->get();
            foreach ($bookings as $booking) {
                $booking->kode_pemesanan = 'BKG-' . strtoupper(substr(uniqid(), -8));
                $booking->save();
            }

            // Setelah semua data terisi, ubah kolom jadi NOT NULL
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('kode_pemesanan', 20)->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'kode_pemesanan')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('kode_pemesanan');
            });
        }
    }
};
