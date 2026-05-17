<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah semua kolom harga dari DECIMAL ke UNSIGNED BIGINT
     * agar tidak ada desimal .00 di database.
     */
    public function up(): void
    {
        // ── layanan.harga ────────────────────────────────────────────────
        if (Schema::hasTable('layanan') && Schema::hasColumn('layanan', 'harga')) {
            DB::statement('ALTER TABLE `layanan` MODIFY COLUMN `harga` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }

        // ── suku_cadang.harga_beli & harga_jual ─────────────────────────
        if (Schema::hasTable('suku_cadang')) {
            if (Schema::hasColumn('suku_cadang', 'harga_beli')) {
                DB::statement('ALTER TABLE `suku_cadang` MODIFY COLUMN `harga_beli` BIGINT UNSIGNED NOT NULL DEFAULT 0');
            }
            if (Schema::hasColumn('suku_cadang', 'harga_jual')) {
                DB::statement('ALTER TABLE `suku_cadang` MODIFY COLUMN `harga_jual` BIGINT UNSIGNED NOT NULL DEFAULT 0');
            }
        }

        // ── item_pemesanan.harga_saat_ini ────────────────────────────────
        if (Schema::hasTable('item_pemesanan') && Schema::hasColumn('item_pemesanan', 'harga_saat_ini')) {
            DB::statement('ALTER TABLE `item_pemesanan` MODIFY COLUMN `harga_saat_ini` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }

        // ── layanan_pemesanan.harga_saat_pesan ───────────────────────────
        if (Schema::hasTable('layanan_pemesanan') && Schema::hasColumn('layanan_pemesanan', 'harga_saat_pesan')) {
            DB::statement('ALTER TABLE `layanan_pemesanan` MODIFY COLUMN `harga_saat_pesan` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }

        // ── pemesanan.total_harga ────────────────────────────────────────
        if (Schema::hasTable('pemesanan') && Schema::hasColumn('pemesanan', 'total_harga')) {
            DB::statement('ALTER TABLE `pemesanan` MODIFY COLUMN `total_harga` BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }

    /**
     * Kembalikan ke DECIMAL(15,2) jika perlu rollback.
     */
    public function down(): void
    {
        $konversi = [
            ['layanan',            'harga'],
            ['suku_cadang',        'harga_beli'],
            ['suku_cadang',        'harga_jual'],
            ['item_pemesanan',     'harga_saat_ini'],
            ['layanan_pemesanan',  'harga_saat_pesan'],
            ['pemesanan',          'total_harga'],
        ];

        foreach ($konversi as [$tabel, $kolom]) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, $kolom)) {
                DB::statement("ALTER TABLE `{$tabel}` MODIFY COLUMN `{$kolom}` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00");
            }
        }
    }
};
