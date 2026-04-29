<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finalisasi skema Indonesia:
     * - Sinkronkan data dari kolom legacy Inggris ke kolom Indonesia.
     * - Pastikan FK di kolom Indonesia tersedia.
     * - Hapus kolom legacy Inggris agar schema konsisten.
     */
    public function up(): void
    {
        $this->sinkronkanKolomWarisan();

        // Operasi FK + drop kolom paling stabil di MySQL.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->pastikanForeignKeyKolomIndonesia();
        $this->hapusKolomWarisanInggris();
    }

    /**
     * Rollback sengaja no-op untuk menghindari rollback destruktif.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }

    private function sinkronkanKolomWarisan(): void
    {
        if (Schema::hasTable('pengguna')) {
            if (Schema::hasColumn('pengguna', 'nama') && Schema::hasColumn('pengguna', 'name')) {
                DB::statement("UPDATE `pengguna` SET `nama` = COALESCE(`nama`, `name`)");
            }
        }

        if (Schema::hasTable('pemesanan')) {
            if (Schema::hasColumn('pemesanan', 'jam_pemesanan') && Schema::hasColumn('pemesanan', 'booking_time')) {
                DB::statement("UPDATE `pemesanan` SET `jam_pemesanan` = COALESCE(`jam_pemesanan`, `booking_time`)");
            }

            if (Schema::hasColumn('pemesanan', 'jam_pemesanan') && Schema::hasColumn('pemesanan', 'jam_booking')) {
                DB::statement("UPDATE `pemesanan` SET `jam_pemesanan` = COALESCE(`jam_pemesanan`, `jam_booking`)");
            }
        }

        if (Schema::hasTable('layanan_pemesanan')) {
            if (Schema::hasColumn('layanan_pemesanan', 'id_pemesanan') && Schema::hasColumn('layanan_pemesanan', 'booking_id')) {
                DB::statement("UPDATE `layanan_pemesanan` SET `id_pemesanan` = COALESCE(`id_pemesanan`, `booking_id`)");
            }

            if (Schema::hasColumn('layanan_pemesanan', 'id_layanan') && Schema::hasColumn('layanan_pemesanan', 'service_id')) {
                DB::statement("UPDATE `layanan_pemesanan` SET `id_layanan` = COALESCE(`id_layanan`, `service_id`)");
            }
        }

        if (Schema::hasTable('item_pemesanan')) {
            if (Schema::hasColumn('item_pemesanan', 'id_pemesanan') && Schema::hasColumn('item_pemesanan', 'booking_id')) {
                DB::statement("UPDATE `item_pemesanan` SET `id_pemesanan` = COALESCE(`id_pemesanan`, `booking_id`)");
            }

            if (Schema::hasColumn('item_pemesanan', 'jumlah') && Schema::hasColumn('item_pemesanan', 'quantity')) {
                DB::statement("UPDATE `item_pemesanan` SET `jumlah` = COALESCE(`jumlah`, `quantity`)");
            }

            if (Schema::hasColumn('item_pemesanan', 'jumlah')) {
                DB::statement("UPDATE `item_pemesanan` SET `jumlah` = 1 WHERE `jumlah` IS NULL");
            }
        }

        if (Schema::hasTable('notifikasi')) {
            if (Schema::hasColumn('notifikasi', 'tipe') && Schema::hasColumn('notifikasi', 'type')) {
                DB::statement("UPDATE `notifikasi` SET `tipe` = COALESCE(`tipe`, `type`)");
            }

            if (Schema::hasColumn('notifikasi', 'judul') && Schema::hasColumn('notifikasi', 'title')) {
                DB::statement("UPDATE `notifikasi` SET `judul` = COALESCE(`judul`, `title`)");
            }

            if (Schema::hasColumn('notifikasi', 'pesan') && Schema::hasColumn('notifikasi', 'message')) {
                DB::statement("UPDATE `notifikasi` SET `pesan` = COALESCE(`pesan`, `message`)");
            }
        }
    }

    private function pastikanForeignKeyKolomIndonesia(): void
    {
        $this->tambahForeignKeyJikaBelumAda('layanan_pemesanan', 'id_pemesanan', 'pemesanan', 'id', 'cascade');
        $this->tambahForeignKeyJikaBelumAda('layanan_pemesanan', 'id_layanan', 'layanan', 'id', 'cascade');

        $this->tambahForeignKeyJikaBelumAda('item_pemesanan', 'id_pemesanan', 'pemesanan', 'id', 'cascade');
        $this->tambahForeignKeyJikaBelumAda('item_pemesanan', 'id_suku_cadang', 'suku_cadang', 'id', 'set null');

        $this->tambahForeignKeyJikaBelumAda('notifikasi', 'id_pemesanan', 'pemesanan', 'id', 'cascade');
    }

    private function hapusKolomWarisanInggris(): void
    {
        $this->hapusKolomLegacyDenganForeignKey('layanan_pemesanan', 'booking_id');
        $this->hapusKolomLegacyDenganForeignKey('layanan_pemesanan', 'service_id');

        $this->hapusKolomLegacyDenganForeignKey('item_pemesanan', 'booking_id');
        $this->hapusKolomLegacyDenganForeignKey('item_pemesanan', 'quantity');

        $this->hapusKolomLegacyDenganForeignKey('notifikasi', 'type');
        $this->hapusKolomLegacyDenganForeignKey('notifikasi', 'title');
        $this->hapusKolomLegacyDenganForeignKey('notifikasi', 'message');

        $this->hapusKolomLegacyDenganForeignKey('pemesanan', 'booking_time');
        $this->hapusKolomLegacyDenganForeignKey('pemesanan', 'jam_booking');

        $this->hapusKolomLegacyDenganForeignKey('pengguna', 'name');
    }

    private function tambahForeignKeyJikaBelumAda(
        string $tabel,
        string $kolom,
        string $tabelReferensi,
        string $kolomReferensi,
        string $aksiSaatHapus
    ): void {
        if (!Schema::hasTable($tabel) || !Schema::hasTable($tabelReferensi) || !Schema::hasColumn($tabel, $kolom)) {
            return;
        }

        if ($this->kolomMemilikiForeignKey($tabel, $kolom)) {
            return;
        }

        Schema::table($tabel, function (Blueprint $table) use ($kolom, $tabelReferensi, $kolomReferensi, $aksiSaatHapus) {
            $foreign = $table->foreign($kolom)->references($kolomReferensi)->on($tabelReferensi);

            if ($aksiSaatHapus === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }

    private function hapusKolomLegacyDenganForeignKey(string $tabel, string $kolom): void
    {
        if (!Schema::hasTable($tabel) || !Schema::hasColumn($tabel, $kolom)) {
            return;
        }

        $this->hapusSemuaForeignKeyPadaKolom($tabel, $kolom);

        Schema::table($tabel, function (Blueprint $table) use ($kolom) {
            $table->dropColumn($kolom);
        });
    }

    private function hapusSemuaForeignKeyPadaKolom(string $tabel, string $kolom): void
    {
        foreach ($this->ambilNamaForeignKeyKolom($tabel, $kolom) as $namaForeignKey) {
            Schema::table($tabel, function (Blueprint $table) use ($namaForeignKey) {
                $table->dropForeign($namaForeignKey);
            });
        }
    }

    private function kolomMemilikiForeignKey(string $tabel, string $kolom): bool
    {
        return count($this->ambilNamaForeignKeyKolom($tabel, $kolom)) > 0;
    }

    /**
     * @return array<int, string>
     */
    private function ambilNamaForeignKeyKolom(string $tabel, string $kolom): array
    {
        $namaBasisData = $this->namaBasisDataAktif();
        if ($namaBasisData === null) {
            return [];
        }

        $hasil = DB::select(
            'SELECT CONSTRAINT_NAME AS nama
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$namaBasisData, $tabel, $kolom]
        );

        $daftarNama = [];
        foreach ($hasil as $baris) {
            if (!empty($baris->nama)) {
                $daftarNama[] = (string) $baris->nama;
            }
        }

        return array_values(array_unique($daftarNama));
    }

    private function namaBasisDataAktif(): ?string
    {
        $baris = DB::selectOne('SELECT DATABASE() AS nama');
        if (!$baris || empty($baris->nama)) {
            return null;
        }

        return (string) $baris->nama;
    }
};
