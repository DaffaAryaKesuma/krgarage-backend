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
        if (!Schema::hasColumn('bookings', 'booking_time')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('booking_time')->after('tanggal_pemesanan'); // Format: "09:00", "10:00"
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'booking_time')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('booking_time');
            });
        }
    }
};
