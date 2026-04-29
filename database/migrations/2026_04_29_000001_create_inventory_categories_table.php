<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $defaultCategories = [
            'Oli',
            'Busi',
            'Kampas Rem',
            'Kampas Kopling',
            'Kopling',
            'Kabel',
            'Filter',
            'Bearing',
            'Karburator',
            'Aki',
            'Lampu',
            'Ban',
            'Lainnya',
        ];

        $now = now();
        DB::table('inventory_categories')->insert(array_map(
            fn (string $name) => [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $defaultCategories,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
