<?php

namespace App\Http\Controllers\Api\Pemilik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Sparepart;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PemilikController extends Controller
{
    /**
     * Helper: Ubah data pemesanan ke format respons standar.
     */
    private function formatResponPemesanan($pemesanan)
    {
        return [
            'id'                => $pemesanan->id,
            'kode_pemesanan'    => $pemesanan->kode_pemesanan,
            'tanggal_pemesanan' => $pemesanan->tanggal_pemesanan,
            'nama_pelanggan'    => $pemesanan->pengguna->nama ?? 'N/A',
            'nama_layanan'      => $pemesanan->layanan->pluck('nama_layanan')->join(', ') ?: 'N/A',
            'total_harga'       => $pemesanan->total_harga ?? 0,
            'status'            => $pemesanan->status,
        ];
    }

    /**
    * Mendapatkan statistik dashboard pemilik.
     */
    public function statistik(Request $request)
    {
        try {
            $hari          = Carbon::today();
            $awalBulanIni  = Carbon::now()->startOfMonth();

            // Omzet hari ini
            $pendapatanHariIni = Booking::selesai()
                ->sudahDibayar()
                ->whereDate('updated_at', $hari)
                ->sum('total_harga');

            // Omzet bulan ini
            $pendapatanBulanIni = Booking::selesai()
                ->sudahDibayar()
                ->where('updated_at', '>=', $awalBulanIni)
                ->sum('total_harga');

            // Unit masuk hari ini (semua status kecuali Cancelled)
            $unitHariIni = Booking::whereDate('created_at', $hari)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->count();

            // Nilai aset stok (jumlah_stok * harga_beli)
            $nilaiStok = Sparepart::all()->sum(function ($item) {
                return ($item->jumlah_stok ?? 0) * ($item->harga_beli ?? 0);
            });

            return response()->json([
                'pendapatan_hari_ini'  => $pendapatanHariIni,
                'pendapatan_bulan_ini' => $pendapatanBulanIni,
                'unit_hari_ini'        => $unitHariIni,
                'nilai_stok'           => $nilaiStok,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    /**
     * Mendapatkan 5 pemesanan terbaru.
     */
    public function pemesananTerbaru(Request $request)
    {
        try {
            $daftarPemesanan = Booking::with(['pengguna', 'vespa', 'layanan'])
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(fn($pemesanan) => $this->formatResponPemesanan($pemesanan));

            return response()->json($daftarPemesanan);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mendapatkan tren pendapatan berdasarkan rentang tanggal.
     */
    public function trenPendapatan(Request $request)
    {
        try {
            $tanggalMulai = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
            $tanggalAkhir = $request->query('end_date', Carbon::now()->toDateString());

            $rentangTanggal = CarbonPeriod::create($tanggalMulai, $tanggalAkhir);

            $labelGrafik    = [];
            $nilaiGrafik    = [];
            $petaPendapatan = [];

            $pendapatanHarian = Booking::selesai()
                ->sudahDibayar()
                ->whereBetween('tanggal_pemesanan', [$tanggalMulai, $tanggalAkhir])
                ->selectRaw('DATE(tanggal_pemesanan) as tanggal, SUM(total_harga) as total')
                ->groupBy('tanggal')
                ->orderBy('tanggal', 'asc')
                ->get();

            // Mapping hasil query ke array asosiatif: ['2026-01-13' => 150000]
            foreach ($pendapatanHarian as $item) {
                $petaPendapatan[$item->tanggal] = (float) $item->total;
            }

            // Loop rentang tanggal untuk mengisi data grafik
            foreach ($rentangTanggal as $tanggal) {
                $kunciTanggal = $tanggal->format('Y-m-d');

                // Format label grafik: "13 Jan", "14 Jan"
                $labelGrafik[] = $tanggal->format('d M');

                // Isi nilai: ambil dari peta jika ada, jika tidak set 0
                $nilaiGrafik[] = $petaPendapatan[$kunciTanggal] ?? 0;
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'labels'        => $labelGrafik,
                    'values'        => $nilaiGrafik,
                    'total_periode' => array_sum($nilaiGrafik),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Tren pendapatan error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memuat data grafik: ' . $e->getMessage(),
                'labels'  => [],
                'values'  => [],
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar transaksi berdasarkan rentang tanggal.
     */
    public function transaksi(Request $request)
    {
        try {
            $tanggalMulai = $request->query('start_date', Carbon::now()->startOfMonth()->toDateString());
            $tanggalAkhir = $request->query('end_date', Carbon::now()->toDateString());

            $daftarTransaksi = Booking::with(['pengguna:id,nama', 'vespa:id,plat_nomor'])
                ->where('status', Booking::STATUS_COMPLETED)
                ->where('status_pembayaran', Booking::PAYMENT_STATUS_PAID)
                ->whereBetween('tanggal_pemesanan', [$tanggalMulai, $tanggalAkhir])
                ->orderBy('tanggal_pemesanan', 'DESC')
                ->get()
                ->map(function ($pemesanan) {
                    return [
                        'id'                => $pemesanan->id,
                        'kode_pemesanan'    => $pemesanan->kode_pemesanan,
                        'tanggal_pemesanan' => $pemesanan->tanggal_pemesanan,
                        'pengguna'          => [
                            'nama' => $pemesanan->pengguna->nama ?? 'N/A',
                        ],
                        'vespa'             => [
                            'plat_nomor' => $pemesanan->vespa->plat_nomor ?? 'N/A',
                        ],
                        'total_harga'       => $pemesanan->total_harga ?? 0,
                        'status'            => $pemesanan->status,
                        'status_pembayaran' => $pemesanan->status_pembayaran,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data'   => $daftarTransaksi,
            ]);

        } catch (\Exception $e) {
            \Log::error('Transaksi error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'error'  => $e->getMessage(),
                'data'   => [],
            ], 500);
        }
    }

    /**
     * Mendapatkan 5 layanan terpopuler.
     */
    public function layananTerpopuler(Request $request)
    {
        try {
            $bulan = $request->query('month');
            $tahun = $request->query('year');

            $daftarLayananTerpopuler = DB::table('layanan')
                ->join('layanan_pemesanan', 'layanan.id', '=', 'layanan_pemesanan.id_layanan')
                ->join('pemesanan', 'layanan_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->where('pemesanan.status', Booking::STATUS_COMPLETED)
                ->where('pemesanan.status_pembayaran', Booking::PAYMENT_STATUS_PAID)
                ->when($bulan, function ($query) use ($bulan) {
                    return $query->whereMonth('pemesanan.tanggal_pemesanan', $bulan);
                })
                ->when($tahun, function ($query) use ($tahun) {
                    return $query->whereYear('pemesanan.tanggal_pemesanan', $tahun);
                })
                ->select(
                    'layanan.id',
                    'layanan.nama_layanan',
                    'layanan.harga',
                    DB::raw('COUNT(layanan_pemesanan.id_pemesanan) as total_pemesanan')
                )
                ->groupBy('layanan.id', 'layanan.nama_layanan', 'layanan.harga')
                ->orderByDesc('total_pemesanan')
                ->limit(5)
                ->get();

            return response()->json($daftarLayananTerpopuler);

        } catch (\Exception $e) {
            \Log::error('Error mendapatkan layanan terpopuler: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mendapatkan 5 suku cadang terlaris.
     */
    public function sukuCadangTerlaris(Request $request)
    {
        try {
            $bulan = $request->query('month');
            $tahun = $request->query('year');

            $daftarSukuCadangTerlaris = DB::table('suku_cadang')
                ->join('item_pemesanan', 'suku_cadang.id', '=', 'item_pemesanan.id_suku_cadang')
                ->join('pemesanan', 'item_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->where('pemesanan.status', Booking::STATUS_COMPLETED)
                ->where('pemesanan.status_pembayaran', Booking::PAYMENT_STATUS_PAID)
                ->when($bulan, function ($query) use ($bulan) {
                    return $query->whereMonth('pemesanan.tanggal_pemesanan', $bulan);
                })
                ->when($tahun, function ($query) use ($tahun) {
                    return $query->whereYear('pemesanan.tanggal_pemesanan', $tahun);
                })
                ->select(
                    'suku_cadang.id',
                    'suku_cadang.nama_suku_cadang as nama_barang',
                    'suku_cadang.jumlah_stok',
                    'suku_cadang.harga_jual',
                    DB::raw('SUM(item_pemesanan.jumlah) as total_terjual'),
                    DB::raw('SUM(item_pemesanan.jumlah * item_pemesanan.harga_saat_ini) as total_pendapatan')
                )
                ->groupBy(
                    'suku_cadang.id',
                    'suku_cadang.nama_suku_cadang',
                    'suku_cadang.jumlah_stok',
                    'suku_cadang.harga_jual'
                )
                ->orderByDesc('total_terjual')
                ->limit(5)
                ->get();

            return response()->json($daftarSukuCadangTerlaris);

        } catch (\Exception $e) {
            \Log::error('Error mendapatkan suku cadang terlaris: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mendapatkan daftar suku cadang dengan stok menipis.
     */
    public function stokMenipis(Request $request)
    {
        try {
            $daftarStokMenipis = Sparepart::all()
                ->filter(function ($item) {
                    return $item->jumlah_stok <= $item->batas_minimal_stok;
                })
                ->sortBy('jumlah_stok')
                ->map(function ($item) {
                    return [
                        'id'           => $item->id,
                        'nama_barang'  => $item->nama_suku_cadang,
                        'kategori'     => $item->kategori,
                        'jumlah_stok'  => $item->jumlah_stok,
                        'minimum_stok' => $item->batas_minimal_stok,
                        'harga_beli'   => $item->harga_beli,
                    ];
                })
                ->values();

            return response()->json($daftarStokMenipis);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }
}