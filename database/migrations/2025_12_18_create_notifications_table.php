<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengguna')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'booking_confirmed',
                'booking_in_progress',
                'booking_completed',
                'booking_cancelled'
            ]);
            $table->string('title');
            $table->text('message');
            $table->boolean('sudah_dibaca')->default(false);
            $table->foreignId('id_pemesanan')->nullable()->constrained('bookings')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['id_pengguna', 'sudah_dibaca']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
