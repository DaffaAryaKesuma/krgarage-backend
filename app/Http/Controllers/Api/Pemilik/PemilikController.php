<?php

namespace App\Http\Controllers\Api\Pemilik;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SukuCadang;
use App\Models\Pemesanan;
use App\Models\ItemPemesanan;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PemilikController extends Controller
{
    use ApiResponseTrait;

    public function statistik(Request $request)
    {
        try {
            $hari = \Carbon\Carbon::today();
            $bulanIni = $hari->month;
            $tahunIni = $hari->year;

            // Pendapatan hari ini (pemesanan selesai & lunas)
            $pendapatanHariIni = Pemesanan::selesai()
                ->sudahDibayar()
                ->whereDate('updated_at', $hari)
                ->sum('total_harga');

            // Pendapatan bulan ini
            $pendapatanBulanIni = Pemesanan::selesai()
                ->sudahDibayar()
                ->whereMonth('updated_at', $bulanIni)
                ->whereYear('updated_at', $tahunIni)
                ->sum('total_harga');

            // Unit servis selesai hari ini
            $unitHariIni = Pemesanan::selesai()
                ->whereDate('updated_at', $hari)
                ->count();

            // Nilai total stok suku cadang
            $nilaiStok = DB::table('suku_cadang')
                ->sum(DB::raw('jumlah_stok * harga_beli'));

            return $this->successResponse('Statistik berhasil dimuat', [
                'pendapatan_hari_ini'  => (float) $pendapatanHariIni,
                'pendapatan_bulan_ini' => (float) $pendapatanBulanIni,
                'unit_hari_ini'        => (int) $unitHariIni,
                'nilai_stok'           => (float) $nilaiStok,
            ]);

        } catch (\Exception $e) {
            Log::error('PemilikController@statistik: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil statistik', 500, $e);
        }
    }

    public function pemesananTerbaru(Request $request)
    {
        try {
            $daftarPemesanan = Pemesanan::with(['pengguna:id,nama', 'layanan:id,nama_layanan'])
                ->select('id', 'kode_pemesanan', 'id_pengguna', 'tanggal_pemesanan', 'status', 'status_pembayaran', 'total_harga')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($p) {
                    return [
                        'id'               => $p->id,
                        'kode_pemesanan'   => $p->kode_pemesanan,
                        'tanggal_pemesanan'=> $p->tanggal_pemesanan,
                        'nama_pelanggan'   => $p->pengguna->nama ?? '-',
                        'nama_layanan'     => $p->layanan->pluck('nama_layanan')->join(', ') ?: '-',
                        'total_harga'      => $p->total_harga,
                        'status'           => $p->status,
                        'status_pembayaran'=> $p->status_pembayaran,
                    ];
                });

            return $this->successResponse('Pemesanan terbaru berhasil dimuat', $daftarPemesanan);

        } catch (\Exception $e) {
            Log::error('PemilikController@pemesananTerbaru: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil pemesanan terbaru', 500, $e);
        }
    }

    public function trenPendapatan(Request $request)
    {
        try {
            $startDate = $request->query('start_date');
            $endDate   = $request->query('end_date');

            if (!$startDate || !$endDate) {
                return $this->errorResponse('Parameter start_date dan end_date wajib diisi', 422);
            }

            $start = \Carbon\Carbon::parse($startDate)->startOfDay();
            $end   = \Carbon\Carbon::parse($endDate)->endOfDay();

            $labels = [];
            $values = [];

            $current = $start->copy();
            while ($current <= $end) {
                $pendapatan = Pemesanan::selesai()
                    ->sudahDibayar()
                    ->whereDate('tanggal_pemesanan', $current->toDateString())
                    ->sum('total_harga');

                $labels[] = $current->format('d M');
                $values[] = (float) $pendapatan;
                $current->addDay();
            }

            return $this->successResponse('Tren pendapatan berhasil dimuat', [
                'labels' => $labels,
                'values' => $values,
            ]);

        } catch (\Exception $e) {
            Log::error('PemilikController@trenPendapatan: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil tren pendapatan', 500, $e);
        }
    }


    public function transaksi(Request $request)
    {
        try {
            $perHalaman = $request->query('per_page', 100);
            $startDate  = $request->query('start_date');
            $endDate    = $request->query('end_date');
            $bulan      = $request->query('month');
            $tahun      = $request->query('year');

            $query = Pemesanan::selesai()
                ->sudahDibayar()
                ->with(['pengguna:id,nama', 'vespa:id,plat_nomor', 'layanan:id,nama_layanan'])
                ->select('id', 'kode_pemesanan', 'id_pengguna', 'id_vespa', 'tanggal_pemesanan', 'status', 'status_pembayaran', 'total_harga', 'updated_at');

            if ($startDate && $endDate) {
                $query->whereBetween('tanggal_pemesanan', [$startDate, $endDate]);
            } elseif ($bulan && $tahun) {
                $query->whereMonth('tanggal_pemesanan', $bulan)->whereYear('tanggal_pemesanan', $tahun);
            } elseif ($tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun);
            }

            $transaksi = $query->orderBy('tanggal_pemesanan', 'desc')->get();

            return $this->successResponse('Transaksi berhasil dimuat', $transaksi);

        } catch (\Exception $e) {
            Log::error('PemilikController@transaksi: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil transaksi', 500, $e);
        }
    }

    public function layananTerpopuler(Request $request)
    {
        try {
            $bulan = $request->query('month', date('m'));
            $tahun = $request->query('year', date('Y'));

            $layanan = DB::table('layanan_pemesanan')
                ->join('layanan', 'layanan_pemesanan.id_layanan', '=', 'layanan.id')
                ->join('pemesanan', 'layanan_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->where('pemesanan.status', Pemesanan::STATUS_SELESAI)
                ->whereMonth('pemesanan.tanggal_pemesanan', $bulan)
                ->whereYear('pemesanan.tanggal_pemesanan', $tahun)
                ->select('layanan.id', 'layanan.nama_layanan', DB::raw('count(*) as total'))
                ->groupBy('layanan.id', 'layanan.nama_layanan')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            return $this->successResponse('Layanan terpopuler berhasil dimuat', $layanan);

        } catch (\Exception $e) {
            Log::error('PemilikController@layananTerpopuler: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil layanan terpopuler', 500, $e);
        }
    }

    public function sukuCadangTerlaris(Request $request)
    {
        try {
            $bulan = $request->query('month', date('m'));
            $tahun = $request->query('year', date('Y'));

            $sukuCadang = ItemPemesanan::select('id_suku_cadang', DB::raw('sum(jumlah) as total_terjual'))
                ->with('sukuCadang:id,nama_suku_cadang')
                ->join('pemesanan', 'item_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->where('pemesanan.status', Pemesanan::STATUS_SELESAI)
                ->whereMonth('pemesanan.tanggal_pemesanan', $bulan)
                ->whereYear('pemesanan.tanggal_pemesanan', $tahun)
                ->groupBy('id_suku_cadang')
                ->orderByDesc('total_terjual')
                ->take(5)
                ->get()
                ->map(fn($item) => [
                    'id'           => $item->id_suku_cadang,
                    'nama_barang'  => $item->sukuCadang->nama_suku_cadang ?? '-',
                    'total_terjual'=> (int) $item->total_terjual,
                ]);

            return $this->successResponse('Suku cadang terlaris berhasil dimuat', $sukuCadang);

        } catch (\Exception $e) {
            Log::error('PemilikController@sukuCadangTerlaris: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil suku cadang terlaris', 500, $e);
        }
    }

    public function stokMenipis(Request $request)
    {
        try {
            $stokMenipis = SukuCadang::whereRaw('jumlah_stok <= batas_minimal_stok')
                ->with('kategori:id,nama')
                ->orderBy('jumlah_stok', 'asc')
                ->get(['id', 'nama_suku_cadang', 'id_kategori', 'jumlah_stok', 'batas_minimal_stok', 'harga_beli'])
                ->map(fn($item) => [
                    'id'           => $item->id,
                    'nama_barang'  => $item->nama_suku_cadang,
                    'kategori'     => $item->kategori->nama ?? '-',
                    'jumlah_stok'  => $item->jumlah_stok,
                    'minimum_stok' => $item->batas_minimal_stok,
                    'harga_beli'   => $item->harga_beli,
                ]);

            return $this->successResponse('Stok menipis berhasil dimuat', $stokMenipis);

        } catch (\Exception $e) {
            Log::error('PemilikController@stokMenipis: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil data stok menipis', 500, $e);
        }
    }

    public function getOnlineMechanicsCount(Request $request)
    {
        try {
            $totalMekanik = User::where('role', 'mekanik')->count();

            // Mekanik dianggap online jika last_seen dalam 5 menit terakhir
            $batasWaktu = \Carbon\Carbon::now()->subMinutes(5);
            $jumlahOnline = User::where('role', 'mekanik')
                ->where('last_seen', '>=', $batasWaktu)
                ->count();

            return $this->successResponse('Jumlah mekanik berhasil dimuat', [
                'online' => $jumlahOnline,
                'total'  => $totalMekanik,
            ]);

        } catch (\Exception $e) {
            Log::error('PemilikController@getOnlineMechanicsCount: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil jumlah mekanik online', 500, $e);
        }
    }

    public function ringkasan(Request $request)
    {
        try {
            $bulan = $request->query('month', date('m'));
            $tahun = $request->query('year', date('Y'));

            $pendapatan = Pemesanan::where('status', Pemesanan::STATUS_SELESAI)
                ->where('status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
                ->whereYear('tanggal_pemesanan', $tahun)
                ->whereMonth('tanggal_pemesanan', $bulan)
                ->sum('total_harga');

            // Hitung pengeluaran riil (modal suku cadang yang terjual)
            $pengeluaran = DB::table('item_pemesanan')
                ->join('pemesanan', 'item_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->join('suku_cadang', 'item_pemesanan.id_suku_cadang', '=', 'suku_cadang.id')
                ->where('pemesanan.status', Pemesanan::STATUS_SELESAI)
                ->where('pemesanan.status_pembayaran', Pemesanan::PAYMENT_STATUS_PAID)
                ->whereYear('pemesanan.tanggal_pemesanan', $tahun)
                ->whereMonth('pemesanan.tanggal_pemesanan', $bulan)
                ->sum(DB::raw('suku_cadang.harga_beli * item_pemesanan.jumlah'));
                
            $keuntungan = $pendapatan - $pengeluaran;

            $totalPemesananSelesai = Pemesanan::where('status', Pemesanan::STATUS_SELESAI)
                ->whereYear('tanggal_pemesanan', $tahun)
                ->whereMonth('tanggal_pemesanan', $bulan)
                ->count();
                

            $mekanikTerbaik = Pemesanan::select('id_mekanik', DB::raw('count(*) as total_pekerjaan'))
                ->with('mekanik:id,nama')
                ->where('status', Pemesanan::STATUS_SELESAI)
                ->whereYear('tanggal_pemesanan', $tahun)
                ->whereMonth('tanggal_pemesanan', $bulan)
                ->whereNotNull('id_mekanik')
                ->groupBy('id_mekanik')
                ->orderByDesc('total_pekerjaan')
                ->first();

            $sukuCadangTerlaris = ItemPemesanan::select('id_suku_cadang', DB::raw('sum(jumlah) as total_terjual'))
                ->with('sukuCadang:id,nama_suku_cadang')
                ->join('pemesanan', 'item_pemesanan.id_pemesanan', '=', 'pemesanan.id')
                ->where('pemesanan.status', Pemesanan::STATUS_SELESAI)
                ->whereYear('pemesanan.tanggal_pemesanan', $tahun)
                ->whereMonth('pemesanan.tanggal_pemesanan', $bulan)
                ->groupBy('id_suku_cadang')
                ->orderByDesc('total_terjual')
                ->take(5)
                ->get();

            return $this->successResponse('Ringkasan berhasil dimuat', [
                'periode' => [
                    'bulan' => $bulan,
                    'tahun' => $tahun
                ],
                'finansial' => [
                    'pendapatan_kotor' => $pendapatan,
                    'pengeluaran' => $pengeluaran,
                    'keuntungan_bersih' => $keuntungan
                ],
                'operasional' => [
                    'total_pemesanan_selesai' => $totalPemesananSelesai,
                ],
                'performa' => [
                    'mekanik_terbaik' => $mekanikTerbaik ? [
                        'nama' => $mekanikTerbaik->mekanik->nama ?? 'Unknown',
                        'total_pekerjaan' => $mekanikTerbaik->total_pekerjaan
                    ] : null,
                    'suku_cadang_terlaris' => $sukuCadangTerlaris
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PemilikController@ringkasan: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil ringkasan dashboard pemilik', 500, $e);
        }
    }

    public function metrikKeuangan(Request $request)
    {
        try {
            $tahun = $request->query('year', date('Y'));
            
            $pendapatanBulanan = Pemesanan::select(
                    DB::raw('MONTH(tanggal_pemesanan) as bulan'),
                    DB::raw('SUM(total_harga) as pendapatan')
                )
                ->where('status', Pemesanan::STATUS_SELESAI)
                ->whereYear('tanggal_pemesanan', $tahun)
                ->groupBy('bulan')
                ->orderBy('bulan')
                ->get();
                
            $formatGrafik = [];
            for ($i = 1; $i <= 12; $i++) {
                $formatGrafik[] = [
                    'bulan' => $i,
                    'pendapatan' => 0
                ];
            }
            
            foreach ($pendapatanBulanan as $item) {
                $formatGrafik[$item->bulan - 1]['pendapatan'] = $item->pendapatan;
            }

            return $this->successResponse('Metrik keuangan berhasil dimuat', [
                'tahun' => $tahun,
                'grafik_pendapatan' => $formatGrafik
            ]);

        } catch (\Exception $e) {
            Log::error('PemilikController@metrikKeuangan: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil metrik keuangan', 500, $e);
        }
    }
}

