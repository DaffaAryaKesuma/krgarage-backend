<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_aktivitas')) {
            return;
        }

        if (Schema::hasColumn('log_aktivitas', 'role_pengguna') && !Schema::hasColumn('log_aktivitas', 'role')) {
            Schema::table('log_aktivitas', function (Blueprint $table) {
                $table->renameColumn('role_pengguna', 'role');
            });
        }

        if (Schema::hasColumn('log_aktivitas', 'id_admin')) {
            $this->dropForeignKeyIfExists('log_aktivitas', 'id_admin');

            Schema::table('log_aktivitas', function (Blueprint $table) {
                $table->dropColumn('id_admin');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('log_aktivitas')) {
            return;
        }

        if (!Schema::hasColumn('log_aktivitas', 'id_admin')) {
            Schema::table('log_aktivitas', function (Blueprint $table) {
                $table->foreignId('id_admin')->nullable()->after('id')->constrained('pengguna')->nullOnDelete();
            });

            DB::table('log_aktivitas')
                ->where('role', 'admin')
                ->whereNotNull('id_pengguna')
                ->update(['id_admin' => DB::raw('id_pengguna')]);
        }

        if (Schema::hasColumn('log_aktivitas', 'role') && !Schema::hasColumn('log_aktivitas', 'role_pengguna')) {
            Schema::table('log_aktivitas', function (Blueprint $table) {
                $table->renameColumn('role', 'role_pengguna');
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->dropForeign([$column]);
                });
            } catch (Throwable) {
                // Some test databases may not create the legacy foreign key.
            }

            return;
        }

        $databaseName = DB::getDatabaseName();
        $constraintName = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraintName) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};
