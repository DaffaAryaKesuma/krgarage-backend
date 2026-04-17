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
            if ($responAkses = $this->pastikanMekanikDitugaskan(auth()->user()->id, $pemesanan)) {
                return $responAkses;
            }

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
        if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) {
            return $responAkses;
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Completed',
            'catatan_mekanik' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $statusLama = $pemesanan->status;
            $statusBaru = $request->status;
            $catatanMekanik = trim((string) $request->catatan_mekanik);

            if ($catatanMekanik === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors'  => [
                        'catatan_mekanik' => ['Catatan mekanik wajib diisi.'],
                    ],
                ], 422);
            }

            if (in_array($statusLama, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status pemesanan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.',
                ], 400);
            }

            if ($statusLama !== Booking::STATUS_IN_PROGRESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mekanik hanya dapat menyelesaikan pemesanan yang berstatus sedang dikerjakan.',
                ], 400);
            }

            // Jika selesai, kurangi stok suku cadang secara otomatis
            if ($statusBaru === Booking::STATUS_COMPLETED) {
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

            $pemesanan->status = $statusBaru;

            if ($statusBaru === Booking::STATUS_COMPLETED && $pemesanan->status_pembayaran !== Booking::PAYMENT_STATUS_PAID) {
                $pemesanan->status_pembayaran = Booking::PAYMENT_STATUS_UNPAID;
            }

            $pemesanan->catatan_mekanik = $catatanMekanik;
            $pemesanan->save();

            // Kirim notifikasi ke pelanggan berdasarkan perubahan status
            if ($statusBaru === Booking::STATUS_COMPLETED) {
                $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);
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
        if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) {
            return $responAkses;
        }

        if ($responStatus = $this->pastikanPemesananMasihBisaDimodifikasi($pemesanan)) {
            return $responStatus;
        }

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
            if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) {
                return $responAkses;
            }

            if ($responStatus = $this->pastikanPemesananMasihBisaDimodifikasi($pemesanan)) {
                return $responStatus;
            }

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
            $idMekanik = $request->user()->id;

            $query = Booking::with(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang'])
                ->where('status', Booking::STATUS_COMPLETED)
                ->where('id_mekanik', $idMekanik);

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

    /**
     * Pastikan hanya mekanik yang ditugaskan yang boleh mengakses / memodifikasi pemesanan.
     */
    private function pastikanMekanikDitugaskan(int $idMekanikLogin, Booking $pemesanan)
    {
        if ((int) $pemesanan->id_mekanik !== $idMekanikLogin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke pemesanan ini.',
            ], 403);
        }

        return null;
    }

    /**
     * Pastikan pemesanan yang dimodifikasi mekanik belum berstatus final.
     */
    private function pastikanPemesananMasihBisaDimodifikasi(Booking $pemesanan)
    {
        if (in_array($pemesanan->status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pemesanan yang sudah selesai atau dibatalkan tidak dapat dimodifikasi.',
            ], 400);
        }

        return null;
    }
}