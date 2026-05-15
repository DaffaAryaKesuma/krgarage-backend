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
        // 1. Ubah nama tabel kategori
        Schema::rename('kategori_inventori', 'kategori_suku_cadang');

        // 2. Ubah kolom timestamp agar sesuai standar Laravel
        Schema::table('kategori_suku_cadang', function (Blueprint $table) {
            $table->renameColumn('dibuat_pada', 'created_at');
            $table->renameColumn('diperbarui_pada', 'updated_at');
        });

        // 3. Hapus kolom kategori yang lama (varchar) dan ganti dengan id_kategori (foreign key)
        Schema::table('suku_cadang', function (Blueprint $table) {
            $table->dropColumn('kategori');
            $table->unsignedBigInteger('id_kategori')->nullable()->after('nama_suku_cadang');
            
            $table->foreign('id_kategori')
                  ->references('id')->on('kategori_suku_cadang')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suku_cadang', function (Blueprint $table) {
            $table->dropForeign(['id_kategori']);
            $table->dropColumn('id_kategori');
            $table->string('kategori')->nullable()->after('nama_suku_cadang');
        });

        Schema::table('kategori_suku_cadang', function (Blueprint $table) {
            $table->renameColumn('created_at', 'dibuat_pada');
            $table->renameColumn('updated_at', 'diperbarui_pada');
        });

        Schema::rename('kategori_suku_cadang', 'kategori_inventori');
    }
};
