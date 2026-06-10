<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('vespa')->update([
            'plat_nomor' => DB::raw('UPPER(TRIM(plat_nomor))'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak bisa mengembalikan kapitalisasi lama dengan aman.
    }
};
