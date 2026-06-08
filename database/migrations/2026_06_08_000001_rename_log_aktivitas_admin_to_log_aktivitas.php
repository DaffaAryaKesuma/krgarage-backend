<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('log_aktivitas_admin') && !Schema::hasTable('log_aktivitas')) {
            Schema::rename('log_aktivitas_admin', 'log_aktivitas');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('log_aktivitas') && !Schema::hasTable('log_aktivitas_admin')) {
            Schema::rename('log_aktivitas', 'log_aktivitas_admin');
        }
    }
};
