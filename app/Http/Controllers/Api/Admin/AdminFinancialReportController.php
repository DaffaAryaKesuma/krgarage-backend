<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFinancialReportController extends Controller
{
    /**
     * Mendapatkan laporan keuangan dengan filter bulan/tahun.
     */
    public function index(Request $request)
    {
        $tahun = $request->query('year', date('Y'));
        $bulan = $request->query('month', null);

        // Query dasar: hanya pemesanan selesai yang status pembayarannya lunas
        $query = Booking::selesai()->sudahDibayar();

        // Filter berdasarkan tahun
        $query->whereYear('tanggal_pemesanan', $tahun);

        // Filter berdasarkan bulan jika ada
        if ($bulan) {
            $query->whereMonth('tanggal_pemesanan', $bulan);
        }

        // Ambil data pemesanan beserta relasinya
        $daftarPemesanan = $query->with(['layanan', 'pengguna', 'vespa', 'itemPemesanan.sukuCadang'])
            ->orderBy('tanggal_pemesanan', 'DESC')
            ->get();

        // Hitung total pendapatan
        $totalPendapatan = $daftarPemesanan->sum(function ($pemesanan) {
            return $pemesanan->total_harga ?? $pemesanan->layanan->sum('pivot.harga_saat_pesan');
        });

        $totalPemesanan = $daftarPemesanan->count();

        // Agregasi data per bulan untuk grafik
        $dataBulanan = DB::table('pemesanan')
            ->select(
                DB::raw('MONTH(tanggal_pemesanan) as bulan'),
                DB::raw('COUNT(*) as total_pemesanan'),
                DB::raw('SUM(COALESCE(total_harga, (SELECT SUM(harga_saat_pesan) FROM layanan_pemesanan WHERE layanan_pemesanan.id_pemesanan = pemesanan.id))) as pendapatan')
            )
            ->where('status', Booking::STATUS_COMPLETED)
            ->where('status_pembayaran', Booking::PAYMENT_STATUS_PAID)
            ->whereYear('tanggal_pemesanan', $tahun)
            ->groupBy(DB::raw('MONTH(tanggal_pemesanan)'))
            ->orderBy('bulan')
            ->get();

        // Layanan terpopuler (paling sering dipesan)
        $layananTerpopuler = DB::table('layanan_pemesanan')
            ->join('layanan', 'layanan_pemesanan.id_layanan', '=', 'layanan.id')
            ->join('pemesanan', 'layanan_pemesanan.id_pemesanan', '=', 'pemesanan.id')
            ->where('pemesanan.status', Booking::STATUS_COMPLETED)
            ->where('pemesanan.status_pembayaran', Booking::PAYMENT_STATUS_PAID)
            ->whereYear('pemesanan.tanggal_pemesanan', $tahun)
            ->when($bulan, function ($q) use ($bulan, $tahun) {
                $q->whereMonth('pemesanan.tanggal_pemesanan', $bulan)
                  ->whereYear('pemesanan.tanggal_pemesanan', $tahun);
            })
            ->select(
                'layanan.nama_layanan',
                DB::raw('COUNT(*) as total_pesanan'),
                DB::raw('SUM(layanan_pemesanan.harga_saat_pesan) as total_pendapatan')
            )
            ->groupBy('layanan.id', 'layanan.nama_layanan')
            ->orderBy('total_pesanan', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'tahun'        => $tahun,
            'bulan'        => $bulan,
            'ringkasan'    => [
                'total_pendapatan'       => $totalPendapatan,
                'total_pemesanan'        => $totalPemesanan,
                'rata_rata_nilai_pesan'  => $totalPemesanan > 0 ? $totalPendapatan / $totalPemesanan : 0,
            ],
            'data_bulanan'       => $dataBulanan,
            'layanan_terpopuler' => $layananTerpopuler,
            'pemesanan'          => $daftarPemesanan,
        ]);
    }
}