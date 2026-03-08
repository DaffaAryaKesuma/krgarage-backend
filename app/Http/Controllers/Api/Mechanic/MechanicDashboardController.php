<?php

namespace App\Http\Controllers\Api\Mechanic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\NotificationService;
use App\Services\BookingSparepartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MechanicDashboardController extends Controller
{
    protected $layananNotifikasi;
    protected $layananSukuCadang;

    public function __construct(NotificationService $layananNotifikasi, BookingSparepartService $layananSukuCadang)
    {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->layananSukuCadang = $layananSukuCadang;
    }

    /**
     * Menampilkan daftar pekerjaan mekanik (pemesanan yang ditugaskan).
     * Mendukung pagination dan filter berdasarkan status, bulan, tahun.
     */
    public function index(Request $request)
    {
        try {
            $idMekanik  = $request->user()->id;
            $perHalaman = $request->query('per_page', 10);
            $bulan      = $request->query('month');
            $tahun      = $request->query('year');

            $query = Booking::with(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang'])
                ->where('id_mekanik', $idMekanik); // Filter hanya pemesanan yang ditugaskan ke mekanik ini

            // Filter berdasarkan status - jika tidak ada, tampilkan semua pemesanan yang ditugaskan
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan bulan dan tahun jika tersedia
            if ($bulan && $tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun)
                      ->whereMonth('tanggal_pemesanan', $bulan);
            } elseif ($tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun);
            } elseif ($bulan) {
                $query->whereMonth('tanggal_pemesanan', $bulan);
            }

            $daftarPemesanan = $query->orderBy('tanggal_pemesanan', 'desc')
                ->orderBy('jam_pemesanan', 'desc')
                ->paginate($perHalaman);

            return response()->json($daftarPemesanan, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar pekerjaan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan detail satu pemesanan.
     */
    public function show(Booking $pemesanan)
    {
        try {
            $pemesanan = $this->layananSukuCadang->ambilPemesananDenganRelasi($pemesanan);

            return response()->json($pemesanan, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail pemesanan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memperbarui status pemesanan oleh mekanik.
     */
    public function updateStatus(Request $request, Booking $pemesanan)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Confirmed,In Progress,Completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Jika selesai, kurangi stok suku cadang secara otomatis
            if ($request->status === Booking::STATUS_COMPLETED) {
                $adaSukuCadang = $pemesanan->itemPemesanan()->exists();

                if ($adaSukuCadang) {
                    $this->layananSukuCadang->kurangiStokSukuCadang($pemesanan);
                }

                // Perbarui tanggal servis terakhir & berikutnya di tabel vespa
                $vespa = $pemesanan->vespa;
                if ($vespa) {
                    $vespa->perbaruiTanggalServisDariPemesanan($pemesanan);
                }
            }

            $pemesanan->status = $request->status;
            $pemesanan->save();

            // Kirim notifikasi ke pelanggan berdasarkan perubahan status
            if ($request->status === Booking::STATUS_COMPLETED) {
                $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);
            } elseif ($request->status === Booking::STATUS_IN_PROGRESS) {
                $this->layananNotifikasi->notifikasiPemesananDiproses($pemesanan);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status pemesanan berhasil diperbarui',
                'data'    => $pemesanan->fresh(['itemPemesanan.sukuCadang']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pemesanan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menambahkan suku cadang ke pemesanan.
     */
    public function tambahSukuCadang(Request $request, Booking $pemesanan)
    {
        $validator = Validator::make($request->all(), [
            'id_suku_cadang' => 'required|exists:suku_cadang,id',
            'jumlah'         => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $hasil = $this->layananSukuCadang->tambahSukuCadang(
                $pemesanan,
                $request->id_suku_cadang,
                $request->jumlah
            );

            if (!$hasil['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $hasil['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $hasil['message'],
                'data'    => $hasil['data'],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan suku cadang ke pemesanan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus suku cadang dari pemesanan.
     */
    public function hapusSukuCadang(Request $request, Booking $pemesanan, BookingItem $itemPemesanan)
    {
        try {
            // Verifikasi item pemesanan milik pemesanan ini
            if ($itemPemesanan->id_pemesanan !== $pemesanan->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item pemesanan tidak ditemukan',
                ], 404);
            }

            $hasil      = $this->layananSukuCadang->hapusSukuCadang($pemesanan, $itemPemesanan->id);
            $kodeStatus = $hasil['status_code'] ?? 200;

            return response()->json([
                'success' => $hasil['success'],
                'message' => $hasil['message'],
            ], $kodeStatus);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia.
     */
    public function daftarSukuCadangTersedia()
    {
        try {
            $daftarSukuCadang = $this->layananSukuCadang->ambilSukuCadangTersedia();

            return response()->json([
                'success' => true,
                'message' => 'Daftar suku cadang tersedia berhasil dimuat',
                'data'    => $daftarSukuCadang,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan riwayat pekerjaan mekanik yang sudah selesai.
     */
    public function riwayat(Request $request)
    {
        try {
            $query = Booking::with(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang'])
                ->where('status', Booking::STATUS_COMPLETED);

            // Opsional: batasi ke riwayat terbaru (misalnya 30 hari terakhir)
            if ($request->has('days')) {
                $hari = (int) $request->days;
                $query->where('updated_at', '>=', now()->subDays($hari));
            }

            $daftarPemesanan = $query->orderBy('updated_at', 'desc')
                ->limit(50) // Batasi ke 50 pemesanan terbaru
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat pekerjaan berhasil dimuat',
                'data'    => $daftarPemesanan,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat riwayat pekerjaan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}