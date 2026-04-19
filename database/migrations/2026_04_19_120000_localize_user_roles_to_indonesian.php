<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah role bahasa Inggris ke role canonical bahasa Indonesia.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pengguna') || !Schema::hasColumn('pengguna', 'role')) {
            return;
        }

        DB::table('pengguna')->where('role', 'customer')->update(['role' => 'pelanggan']);
        DB::table('pengguna')->where('role', 'owner')->update(['role' => 'pemilik']);
        DB::table('pengguna')->where('role', 'mechanic')->update(['role' => 'mekanik']);

        DB::table('pengguna')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'pelanggan']);

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `pengguna` MODIFY `role` VARCHAR(255) NOT NULL DEFAULT 'pelanggan'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE \"pengguna\" ALTER COLUMN \"role\" SET DEFAULT 'pelanggan'");
        }
    }

    /**
     * Kembalikan role canonical Indonesia ke role lama Inggris.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pengguna') || !Schema::hasColumn('pengguna', 'role')) {
            return;
        }

        DB::table('pengguna')->where('role', 'pelanggan')->update(['role' => 'customer']);
        DB::table('pengguna')->where('role', 'pemilik')->update(['role' => 'owner']);

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `pengguna` MODIFY `role` VARCHAR(255) NOT NULL DEFAULT 'customer'");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE \"pengguna\" ALTER COLUMN \"role\" SET DEFAULT 'customer'");
        }
    }
};
