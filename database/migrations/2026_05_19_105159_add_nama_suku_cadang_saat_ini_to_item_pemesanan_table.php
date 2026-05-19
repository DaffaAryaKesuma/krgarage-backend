<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_pemesanan', function (Blueprint $table) {
            $table->string('nama_suku_cadang_saat_ini')->nullable()->after('id_suku_cadang');
        });

        // Backfill / Update data lama: Salin nama_suku_cadang dari tabel suku_cadang ke kolom baru
        DB::statement('
            UPDATE item_pemesanan ip
            JOIN suku_cadang sc ON ip.id_suku_cadang = sc.id
            SET ip.nama_suku_cadang_saat_ini = sc.nama_suku_cadang
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_pemesanan', function (Blueprint $table) {
            $table->dropColumn('nama_suku_cadang_saat_ini');
        });
    }
};
