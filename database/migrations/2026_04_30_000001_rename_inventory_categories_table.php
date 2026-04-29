<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename table
        Schema::rename('inventory_categories', 'kategori_inventori');

        // Rename columns
        Schema::table('kategori_inventori', function (Blueprint $table) {
            $table->renameColumn('name', 'nama');
            $table->renameColumn('created_at', 'dibuat_pada');
            $table->renameColumn('updated_at', 'diperbarui_pada');
        });
    }

    public function down(): void
    {
        Schema::table('kategori_inventori', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('dibuat_pada', 'created_at');
            $table->renameColumn('diperbarui_pada', 'updated_at');
        });

        Schema::rename('kategori_inventori', 'inventory_categories');
    }
};
