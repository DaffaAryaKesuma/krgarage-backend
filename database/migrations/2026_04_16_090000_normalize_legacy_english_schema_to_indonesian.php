<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinkronkan schema lama (nama tabel/kolom Inggris) ke schema runtime saat ini (Indonesia).
     *
     * Migration ini aman dijalankan berulang karena seluruh operasi bersifat kondisional.
     */
    public function up(): void
    {
        $this->renameTableIfNeeded('users', 'pengguna');
        $this->renameTableIfNeeded('services', 'layanan');
        $this->renameTableIfNeeded('vespas', 'vespa');
        $this->renameTableIfNeeded('bookings', 'pemesanan');
        $this->renameTableIfNeeded('booking_service', 'layanan_pemesanan');
        $this->renameTableIfNeeded('notifications', 'notifikasi');
        $this->renameTableIfNeeded('spareparts', 'suku_cadang');
        $this->renameTableIfNeeded('booking_items', 'item_pemesanan');

        $this->syncPenggunaColumns();
        $this->syncPemesananColumns();
        $this->syncLayananPemesananColumns();
        $this->syncNotifikasiColumns();
        $this->syncItemPemesananColumns();
    }

    /**
     * Rollback dibiarkan no-op untuk mencegah rollback destruktif pada data produksi.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }

    private function renameTableIfNeeded(string $oldName, string $newName): void
    {
        if (Schema::hasTable($oldName) && !Schema::hasTable($newName)) {
            Schema::rename($oldName, $newName);
        }
    }

    private function syncPenggunaColumns(): void
    {
        if (!Schema::hasTable('pengguna')) {
            return;
        }

        if (!Schema::hasColumn('pengguna', 'nama')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->string('nama')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('pengguna', 'name')) {
            DB::statement("UPDATE `pengguna` SET `nama` = COALESCE(`nama`, `name`)");
        }

        if (!Schema::hasColumn('pengguna', 'no_telepon')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->string('no_telepon', 20)->nullable()->after('email');
            });
        }

        if (Schema::hasColumn('pengguna', 'no_telepon')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement("UPDATE `pengguna` SET `no_telepon` = COALESCE(NULLIF(`no_telepon`, ''), '08' || printf('%010d', `id`))");
            } else {
                DB::statement("UPDATE `pengguna` SET `no_telepon` = COALESCE(NULLIF(`no_telepon`, ''), CONCAT('08', LPAD(`id`, 10, '0'))) ");
            }
        }

        if (!Schema::hasColumn('pengguna', 'role')) {
            Schema::table('pengguna', function (Blueprint $table) {
                $table->string('role')->default('pelanggan')->after('password');
            });
        }

        if (Schema::hasColumn('pengguna', 'role')) {
            DB::statement("UPDATE `pengguna` SET `role` = 'pelanggan' WHERE `role` IS NULL OR `role` = ''");
        }
    }

    private function syncPemesananColumns(): void
    {
        if (!Schema::hasTable('pemesanan')) {
            return;
        }

        if (!Schema::hasColumn('pemesanan', 'jam_pemesanan')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->time('jam_pemesanan')->nullable()->after('tanggal_pemesanan');
            });
        }

        if (Schema::hasColumn('pemesanan', 'booking_time')) {
            DB::statement("UPDATE `pemesanan` SET `jam_pemesanan` = COALESCE(`jam_pemesanan`, `booking_time`)");
        }

        if (Schema::hasColumn('pemesanan', 'jam_booking')) {
            DB::statement("UPDATE `pemesanan` SET `jam_pemesanan` = COALESCE(`jam_pemesanan`, `jam_booking`)");
        }

        if (!Schema::hasColumn('pemesanan', 'total_harga')) {
            Schema::table('pemesanan', function (Blueprint $table) {
                $table->decimal('total_harga', 12, 2)->default(0)->after('catatan_pelanggan');
            });
        }

        DB::statement("UPDATE `pemesanan` SET `total_harga` = 0 WHERE `total_harga` IS NULL");
    }

    private function syncLayananPemesananColumns(): void
    {
        if (!Schema::hasTable('layanan_pemesanan')) {
            return;
        }

        if (!Schema::hasColumn('layanan_pemesanan', 'id_pemesanan')) {
            Schema::table('layanan_pemesanan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_pemesanan')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('layanan_pemesanan', 'booking_id')) {
            DB::statement("UPDATE `layanan_pemesanan` SET `id_pemesanan` = COALESCE(`id_pemesanan`, `booking_id`)");
        }

        if (!Schema::hasColumn('layanan_pemesanan', 'id_layanan')) {
            Schema::table('layanan_pemesanan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_layanan')->nullable()->after('id_pemesanan');
            });
        }

        if (Schema::hasColumn('layanan_pemesanan', 'service_id')) {
            DB::statement("UPDATE `layanan_pemesanan` SET `id_layanan` = COALESCE(`id_layanan`, `service_id`)");
        }

        if (!Schema::hasColumn('layanan_pemesanan', 'harga_saat_pesan')) {
            Schema::table('layanan_pemesanan', function (Blueprint $table) {
                $table->decimal('harga_saat_pesan', 10, 2)->nullable()->after('id_layanan');
            });
        }

        if (Schema::hasTable('layanan')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement(
                    "UPDATE `layanan_pemesanan`
                     SET `harga_saat_pesan` = COALESCE(
                        `harga_saat_pesan`,
                        (SELECT `harga` FROM `layanan` WHERE `layanan`.`id` = `layanan_pemesanan`.`id_layanan`),
                        0
                     )"
                );
            } else {
                DB::statement(
                    "UPDATE `layanan_pemesanan` lp
                     LEFT JOIN `layanan` l ON l.id = lp.id_layanan
                     SET lp.harga_saat_pesan = COALESCE(lp.harga_saat_pesan, l.harga, 0)"
                );
            }
        }
    }

    private function syncNotifikasiColumns(): void
    {
        if (!Schema::hasTable('notifikasi')) {
            return;
        }

        if (!Schema::hasColumn('notifikasi', 'tipe')) {
            Schema::table('notifikasi', function (Blueprint $table) {
                $table->string('tipe', 64)->nullable()->after('id_pengguna');
            });
        }

        if (Schema::hasColumn('notifikasi', 'type')) {
            DB::statement("UPDATE `notifikasi` SET `tipe` = COALESCE(`tipe`, `type`)");
        }

        if (!Schema::hasColumn('notifikasi', 'judul')) {
            Schema::table('notifikasi', function (Blueprint $table) {
                $table->string('judul')->nullable()->after('tipe');
            });
        }

        if (Schema::hasColumn('notifikasi', 'title')) {
            DB::statement("UPDATE `notifikasi` SET `judul` = COALESCE(`judul`, `title`)");
        }

        if (!Schema::hasColumn('notifikasi', 'pesan')) {
            Schema::table('notifikasi', function (Blueprint $table) {
                $table->text('pesan')->nullable()->after('judul');
            });
        }

        if (Schema::hasColumn('notifikasi', 'message')) {
            DB::statement("UPDATE `notifikasi` SET `pesan` = COALESCE(`pesan`, `message`)");
        }
    }

    private function syncItemPemesananColumns(): void
    {
        if (!Schema::hasTable('item_pemesanan')) {
            return;
        }

        if (!Schema::hasColumn('item_pemesanan', 'id_pemesanan')) {
            Schema::table('item_pemesanan', function (Blueprint $table) {
                $table->unsignedBigInteger('id_pemesanan')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('item_pemesanan', 'booking_id')) {
            DB::statement("UPDATE `item_pemesanan` SET `id_pemesanan` = COALESCE(`id_pemesanan`, `booking_id`)");
        }

        if (!Schema::hasColumn('item_pemesanan', 'jumlah')) {
            Schema::table('item_pemesanan', function (Blueprint $table) {
                $table->integer('jumlah')->nullable()->after('id_suku_cadang');
            });
        }

        if (Schema::hasColumn('item_pemesanan', 'quantity')) {
            DB::statement("UPDATE `item_pemesanan` SET `jumlah` = COALESCE(`jumlah`, `quantity`)");
        }

        DB::statement("UPDATE `item_pemesanan` SET `jumlah` = 1 WHERE `jumlah` IS NULL");
    }
};
