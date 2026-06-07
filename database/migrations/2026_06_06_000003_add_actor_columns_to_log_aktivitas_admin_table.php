<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_aktivitas_admin', function (Blueprint $table) {
            $table->foreignId('id_pengguna')->nullable()->after('id_admin')->constrained('pengguna')->nullOnDelete();
            $table->string('role_pengguna')->nullable()->after('id_pengguna');
        });

        DB::table('log_aktivitas_admin')
            ->leftJoin('pengguna', 'log_aktivitas_admin.id_admin', '=', 'pengguna.id')
            ->whereNull('log_aktivitas_admin.id_pengguna')
            ->update([
                'log_aktivitas_admin.id_pengguna' => DB::raw('log_aktivitas_admin.id_admin'),
                'log_aktivitas_admin.role_pengguna' => DB::raw('pengguna.role'),
            ]);
    }

    public function down(): void
    {
        Schema::table('log_aktivitas_admin', function (Blueprint $table) {
            $table->dropForeign(['id_pengguna']);
            $table->dropColumn(['id_pengguna', 'role_pengguna']);
        });
    }
};
