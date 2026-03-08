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
        Schema::table('vespas', function (Blueprint $table) {
            $table->date('tanggal_servis_terakhir')->nullable()->after('plat_nomor');
            $table->integer('jeda_hari_servis')->default(30)->after('tanggal_servis_terakhir'); // Default 1 bulan
            $table->date('tanggal_servis_selanjutnya')->nullable()->after('jeda_hari_servis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vespas', function (Blueprint $table) {
            $table->dropColumn(['tanggal_servis_terakhir', 'jeda_hari_servis', 'tanggal_servis_selanjutnya']);
        });
    }
};
