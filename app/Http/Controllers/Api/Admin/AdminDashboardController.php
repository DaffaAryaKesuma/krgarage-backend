<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Mendapatkan statistik dashboard admin.
     */
    public function statistik()
    {
        $hari = Carbon::today();

        // Gunakan query builder dengan select untuk optimasi - hanya ambil jumlah, tidak perlu fetch semua data
        $statistik = [
            'pemesanan_hari_ini' => Pemesanan::whereDate('tanggal_pemesanan', $hari)
                ->where('status', '!=', Pemesanan::STATUS_BATAL)
                ->count(),
            'sedang_dikerjakan'  => Pemesanan::dikerjakan()->count(),
            'selesai_hari_ini'   => Pemesanan::selesai()
                ->whereDate('updated_at', $hari)
                ->count(),
        ];

        return response()->json($statistik);
    }

    /**
     * Mendapatkan 5 pemesanan terbaru.
     */
    public function pemesananTerbaru()
    {
        // Gunakan select() untuk mengambil kolom tertentu saja - query lebih cepat
        $daftarPemesanan = Pemesanan::select('id', 'kode_pemesanan', 'id_pengguna', 'id_vespa', 'id_mekanik', 'tanggal_pemesanan', 'status', 'status_pembayaran', 'created_at')
            ->with(['pengguna:id,nama', 'vespa:id,model', 'mekanik:id,nama'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($daftarPemesanan);
    }


}
