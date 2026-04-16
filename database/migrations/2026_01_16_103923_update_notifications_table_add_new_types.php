<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('booking_confirmed', 'booking_in_progress', 'booking_completed', 'booking_cancelled', 'booking_assigned', 'low_stock') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('booking_confirmed', 'booking_in_progress', 'booking_completed', 'booking_cancelled') NOT NULL");
    }
};
