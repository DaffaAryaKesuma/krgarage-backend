<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrasi nilai kolom `tipe` di tabel notifikasi
     * dari bahasa Inggris ke bahasa Indonesia.
     */
    public function up(): void
    {
        if (!Schema::hasTable('notifikasi')) {
            return;
        }

        $pemetaan = [
            'booking_confirmed'   => 'pemesanan_dikonfirmasi',
            'booking_assigned'    => 'pemesanan_ditugaskan',
            'booking_in_progress' => 'pemesanan_diproses',
            'booking_completed'   => 'pemesanan_selesai',
            'booking_cancelled'   => 'pemesanan_dibatalkan',
            'booking_updated'     => 'pemesanan_diperbarui',
            'booking_deleted'     => 'pemesanan_dihapus',
            'low_stock'           => 'stok_menipis',
            'payment_received'    => 'pembayaran_diterima',
            // Nilai campuran yang mungkin ada
            'pemesanan_confirmed'   => 'pemesanan_dikonfirmasi',
            'pemesanan_in_progress' => 'pemesanan_diproses',
            'pemesanan_completed'   => 'pemesanan_selesai',
            'pemesanan_cancelled'   => 'pemesanan_dibatalkan',
            'pemesanan_assigned'    => 'pemesanan_ditugaskan',
            'pembayaran_received'   => 'pembayaran_diterima',
        ];

        foreach ($pemetaan as $lama => $baru) {
            DB::table('notifikasi')
                ->where('tipe', $lama)
                ->update(['tipe' => $baru]);
        }
    }

    public function down(): void
    {
        // Rollback intentionally no-op karena data lama tidak perlu dikembalikan
    }
};
