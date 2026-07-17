<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLaporanKeuanganController extends Controller
{
    /**
     * Mendapatkan laporan keuangan dengan filter bulan/tahun.
     */
    public function index(Request $request)
    {
        $tahun = $request->query('year', date('Y'));
        $bulan = $request->query('month', null);

        // Query dasar: hanya pemesanan yang status pembayarannya lunas
        $query = Pemesanan::sudahDibayar();

        // Filter berdasarkan waktu pembayaran lunas
        $query->whereYear('paid_at', $tahun);

        if ($bulan) {
            $query->whereMonth('paid_at', $bulan);
        }

        // Ambil data pemesanan beserta relasinya
        $daftarPemesanan = $query->with(['layanan', 'pengguna', 'vespa', 'itemPemesanan.sukuCadang'])
            ->orderBy('paid_at', 'DESC')
            ->get();

        // Hitung total pendapatan
        $totalPendapatan = $daftarPemesanan->sum(function ($pemesanan) {
            return $pemesanan->total_harga ?? $pemesanan->layanan->sum('pivot.harga_saat_pesan');
        });

        $totalPemesanan = $daftarPemesanan->count();

        // Agregasi data per bulan untuk grafik. Ekspresi dibedakan agar laporan
        // konsisten di MySQL produksi dan SQLite pengujian.
        $ekspresiBulan = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', paid_at) AS INTEGER)"
            : 'MONTH(paid_at)';

        $dataBulanan = DB::table('pemesanan')
            ->select(
                DB::raw("{$ekspresiBulan} as bulan"),
                DB::raw('COUNT(*) as total_pemesanan'),
                DB::raw('SUM(COALESCE(total_harga, (SELECT SUM(harga_saat_pesan) FROM layanan_pemesanan WHERE layanan_pemesanan.id_pemesanan = pemesanan.id))) as pendapatan')
            )
            ->where('status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
            ->whereYear('paid_at', $tahun)
            ->groupBy(DB::raw($ekspresiBulan))
            ->orderBy('bulan')
            ->get();

        // Layanan terpopuler (paling sering dipesan)
        $layananTerpopuler = DB::table('layanan_pemesanan')
            ->join('layanan', 'layanan_pemesanan.id_layanan', '=', 'layanan.id')
            ->join('pemesanan', 'layanan_pemesanan.id_pemesanan', '=', 'pemesanan.id')
            ->where('pemesanan.status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
            ->whereYear('pemesanan.paid_at', $tahun)
            ->when($bulan, function ($q) use ($bulan, $tahun) {
                $q->whereMonth('pemesanan.paid_at', $bulan)
                  ->whereYear('pemesanan.paid_at', $tahun);
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

