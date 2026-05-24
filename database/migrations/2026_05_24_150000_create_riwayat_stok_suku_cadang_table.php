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
        Schema::create('riwayat_stok_suku_cadang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_suku_cadang')->constrained('suku_cadang')->cascadeOnDelete();
            $table->foreignId('id_admin')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('jumlah');
            $table->unsignedBigInteger('harga_beli_satuan');
            $table->unsignedBigInteger('total_pengeluaran');
            $table->unsignedInteger('stok_sebelum');
            $table->unsignedInteger('stok_sesudah');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_stok_suku_cadang');
    }
};
