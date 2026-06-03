<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 80);
            $table->string('model_type', 120)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'id']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_events');
    }
};
