<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('pemesanan') && !Schema::hasColumn('pemesanan', 'status_pembayaran')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->string('status_pembayaran')->default('Belum Lunas');
            });
        }

        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'status_pembayaran')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('status_pembayaran')->default('Belum Lunas');
            });
        }

        if (Schema::hasTable('pemesanan') && Schema::hasColumn('pemesanan', 'status_pembayaran')) {
            DB::table('pemesanan')
                ->whereNull('status_pembayaran')
                ->orWhere('status_pembayaran', '')
                ->update(['status_pembayaran' => 'Belum Lunas']);
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'status_pembayaran')) {
            DB::table('bookings')
                ->whereNull('status_pembayaran')
                ->orWhere('status_pembayaran', '')
                ->update(['status_pembayaran' => 'Belum Lunas']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pemesanan') && Schema::hasColumn('pemesanan', 'status_pembayaran')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->dropColumn('status_pembayaran');
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'status_pembayaran')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('status_pembayaran');
            });
        }
    }
};
