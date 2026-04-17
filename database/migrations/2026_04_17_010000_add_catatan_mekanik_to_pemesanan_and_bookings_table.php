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
        if (Schema::hasTable('pemesanan') && !Schema::hasColumn('pemesanan', 'catatan_mekanik')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->text('catatan_mekanik')->nullable()->after('catatan_pelanggan');
            });
        }

        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'catatan_mekanik')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->text('catatan_mekanik')->nullable()->after('catatan_pelanggan');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pemesanan') && Schema::hasColumn('pemesanan', 'catatan_mekanik')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->dropColumn('catatan_mekanik');
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'catatan_mekanik')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('catatan_mekanik');
            });
        }
    }
};
