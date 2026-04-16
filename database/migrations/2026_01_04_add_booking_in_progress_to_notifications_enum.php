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

        // Modify ENUM to add 'booking_in_progress' value
        DB::statement("
            ALTER TABLE notifications 
            MODIFY COLUMN type ENUM(
                'booking_confirmed', 
                'booking_in_progress', 
                'booking_completed', 
                'booking_cancelled'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Remove 'booking_in_progress' from ENUM
        // First, delete any records with this type to prevent data loss errors
        DB::statement("DELETE FROM notifications WHERE type = 'booking_in_progress'");
        
        // Then modify the ENUM back to original values
        DB::statement("
            ALTER TABLE notifications 
            MODIFY COLUMN type ENUM(
                'booking_confirmed', 
                'booking_completed', 
                'booking_cancelled'
            ) NOT NULL
        ");
    }
};