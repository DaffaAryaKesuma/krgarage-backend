<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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
            'pemesanan_hari_ini' => Booking::whereDate('tanggal_pemesanan', $hari)->count(),
            'sedang_diproses'    => Booking::diproses()->count(),
            'selesai_hari_ini'   => Booking::selesai()
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
        $daftarPemesanan = Booking::select('id', 'kode_pemesanan', 'id_pengguna', 'id_vespa', 'id_mekanik', 'tanggal_pemesanan', 'status', 'created_at')
            ->with(['pengguna:id,nama', 'vespa:id,model', 'mekanik:id,nama'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($daftarPemesanan);
    }

    /**
     * Mendapatkan ringkasan lengkap dashboard.
     * Mencakup: total pendapatan, jumlah pemesanan, layanan terpopuler, dan grafik pendapatan (7 bulan).
     */
    public function ringkasan()
    {
        // 1. Total pendapatan (hanya pemesanan yang selesai)
        $totalPendapatan = Booking::selesai()->sum('total_harga');

        // 2. Jumlah pemesanan
        $totalPemesanan     = Booking::count();
        $pemesananSelesai   = Booking::selesai()->count();
        $pemesananMenunggu  = Booking::menunggu()->count();

        // 3. Top 5 layanan terpopuler
        $layananTerpopuler = DB::table('layanan_pemesanan')
            ->join('layanan', 'layanan_pemesanan.id_layanan', '=', 'layanan.id')
            ->join('pemesanan', 'layanan_pemesanan.id_pemesanan', '=', 'pemesanan.id')
            ->where('pemesanan.status', Booking::STATUS_COMPLETED)
            ->select('layanan.nama_layanan', DB::raw('count(*) as total'))
            ->groupBy('layanan.id', 'layanan.nama_layanan')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 4. Data grafik pendapatan (7 bulan terakhir)
        $dataGrafik = [];
        for ($i = 6; $i >= 0; $i--) {
            $tanggal    = Carbon::now()->subMonths($i);
            $namaBulan  = $tanggal->format('F');
            $nomorBulan = $tanggal->month;
            $tahun      = $tanggal->year;

            $pendapatanBulanan = Booking::selesai()
                ->whereMonth('updated_at', $nomorBulan)
                ->whereYear('updated_at', $tahun)
                ->sum('total_harga');

            $dataGrafik[] = [
                'bulan'      => $namaBulan,
                'pendapatan' => $pendapatanBulanan,
            ];
        }

        return response()->json([
            'total_pendapatan'    => $totalPendapatan,
            'total_pemesanan'     => $totalPemesanan,
            'pemesanan_selesai'   => $pemesananSelesai,
            'pemesanan_menunggu'  => $pemesananMenunggu,
            'layanan_terpopuler'  => $layananTerpopuler,
            'data_grafik'         => $dataGrafik,
        ]);
    }
}