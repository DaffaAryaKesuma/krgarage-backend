<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah kolom tipe notifikasi menjadi VARCHAR agar penambahan tipe baru tidak memicu error enum.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('notifikasi') && Schema::hasColumn('notifikasi', 'tipe')) {
            DB::statement("ALTER TABLE `notifikasi` MODIFY COLUMN `tipe` VARCHAR(64) NULL");
        }

        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'type')) {
            DB::statement("ALTER TABLE `notifications` MODIFY COLUMN `type` VARCHAR(64) NULL");
        }
    }

    /**
     * Rollback tidak dilakukan untuk menghindari rollback destruktif pada data tipe notifikasi baru.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
