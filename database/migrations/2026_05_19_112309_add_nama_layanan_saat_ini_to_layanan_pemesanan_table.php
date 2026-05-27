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
        Schema::table('layanan_pemesanan', function (Blueprint $table) {
            $table->string('nama_layanan_saat_ini')->nullable()->after('id_layanan');
        });

        // Backfill / Update data lama: Salin nama_layanan dari tabel layanan ke kolom baru
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE layanan_pemesanan
                SET nama_layanan_saat_ini = (
                    SELECT nama_layanan
                    FROM layanan
                    WHERE layanan.id = layanan_pemesanan.id_layanan
                )
            ');
        } else {
            DB::statement('
                UPDATE layanan_pemesanan lp
                JOIN layanan l ON lp.id_layanan = l.id
                SET lp.nama_layanan_saat_ini = l.nama_layanan
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layanan_pemesanan', function (Blueprint $table) {
            $table->dropColumn('nama_layanan_saat_ini');
        });
    }
};
