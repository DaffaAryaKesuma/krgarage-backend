<?php

use App\Models\Pemesanan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pemesanan')) {
            return;
        }

        Schema::table('pemesanan', function (Blueprint $table) {
            if (!Schema::hasColumn('pemesanan', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('pemesanan', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status_pembayaran');
            }
        });

        DB::table('pemesanan')
            ->where('status', Pemesanan::STATUS_SELESAI)
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);

        DB::table('pemesanan')
            ->where('status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('pemesanan')) {
            return;
        }

        Schema::table('pemesanan', function (Blueprint $table) {
            if (Schema::hasColumn('pemesanan', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('pemesanan', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
