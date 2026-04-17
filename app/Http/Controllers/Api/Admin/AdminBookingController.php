<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\BookingSparepartService;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    protected $layananNotifikasi;
    protected $layananSukuCadang;

    public function __construct(NotificationService $layananNotifikasi, BookingSparepartService $layananSukuCadang)
    {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->layananSukuCadang = $layananSukuCadang;
    }

    /**
     * Menampilkan semua data pemesanan untuk admin dengan pagination.
     */
    public function index(Request $request)
    {
        $perHalaman = $request->query('per_page', 10);

        // Mengambil semua pemesanan, diurutkan dari yang terbaru,
        // dan menyertakan data relasi (pengguna, vespa, layanan, mekanik)
        $daftarPemesanan = Booking::with(['pengguna', 'vespa', 'layanan', 'mekanik'])
                            ->latest()
                            ->paginate($perHalaman);

        return response()->json($daftarPemesanan);
    }

    /**
     * Menampilkan detail satu pemesanan spesifik.
     */
    public function show(Booking $pemesanan)
    {
        $pemesanan = $this->layananSukuCadang->ambilPemesananDenganRelasi($pemesanan);

        return response()->json($pemesanan);
    }

    /**
     * Memperbarui status pemesanan.
     */
    public function updateStatus(Request $request, Booking $pemesanan)
    {
        $dataTervalidasi = $request->validate([
            'status' => 'required|string|in:Pending,Confirmed,In Progress,Completed,Cancelled',
        ]);

        $statusBaru = $dataTervalidasi['status'];
        $statusLama = $pemesanan->status;

        if ($statusLama === $statusBaru) {
            return response()->json([
                'message'   => 'Status pemesanan sudah berada pada nilai yang sama.',
                'pemesanan' => $pemesanan,
            ]);
        }

        if (in_array($statusLama, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true)) {
            return response()->json([
                'message' => 'Status pemesanan yang sudah selesai atau dibatalkan tidak dapat diubah lagi.',
            ], 400);
        }

        $transisiValid = [
            Booking::STATUS_PENDING => [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
            ],
            Booking::STATUS_CONFIRMED => [
                Booking::STATUS_IN_PROGRESS,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
            ],
            Booking::STATUS_IN_PROGRESS => [
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
            ],
        ];

        if (!isset($transisiValid[$statusLama]) || !in_array($statusBaru, $transisiValid[$statusLama], true)) {
            return response()->json([
                'message' => "Transisi status dari {$statusLama} ke {$statusBaru} tidak diperbolehkan.",
            ], 400);
        }

        $pemesanan->status = $statusBaru;

        if ($statusBaru === Booking::STATUS_COMPLETED && $pemesanan->status_pembayaran !== Booking::PAYMENT_STATUS_PAID) {
            $pemesanan->status_pembayaran = Booking::PAYMENT_STATUS_UNPAID;
        }

        $pemesanan->save();

        // Trigger notifikasi berdasarkan status baru
        if ($statusBaru === Booking::STATUS_COMPLETED && $statusLama !== Booking::STATUS_COMPLETED) {
            // Kurangi stok suku cadang saat pemesanan selesai
            $this->layananSukuCadang->kurangiStokSukuCadang($pemesanan);

            // Perbarui tanggal servis terakhir di vespa
            if ($pemesanan->vespa) {
                $pemesanan->vespa->perbaruiTanggalServisDariPemesanan($pemesanan);
            }

            // Buat notifikasi
            $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);
        } elseif ($statusBaru === Booking::STATUS_CONFIRMED && $statusLama === Booking::STATUS_PENDING) {
            $this->layananNotifikasi->notifikasiPemesananDikonfirmasi($pemesanan);
        } elseif ($statusBaru === Booking::STATUS_IN_PROGRESS && ($statusLama === Booking::STATUS_CONFIRMED || $statusLama === Booking::STATUS_PENDING)) {
            $this->layananNotifikasi->notifikasiPemesananDiproses($pemesanan);
        } elseif ($statusBaru === Booking::STATUS_CANCELLED) {
            $this->layananNotifikasi->notifikasiPemesananDibatalkan($pemesanan);
        }

        return response()->json([
            'message'   => 'Status pemesanan berhasil diperbarui!',
            'pemesanan' => $pemesanan,
        ]);
    }

    /**
     * Memperbarui status pembayaran pemesanan.
     */
    public function updatePaymentStatus(Request $request, Booking $pemesanan)
    {
        $dataTervalidasi = $request->validate([
            'status_pembayaran' => 'required|string|in:Belum Lunas,Lunas',
        ]);

        if ($pemesanan->status !== Booking::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Status pembayaran hanya dapat diubah setelah servis selesai.',
            ], 400);
        }

        $statusPembayaranBaru = $dataTervalidasi['status_pembayaran'];

        if ($pemesanan->status_pembayaran === $statusPembayaranBaru) {
            return response()->json([
                'message'   => 'Status pembayaran sudah berada pada nilai yang sama.',
                'pemesanan' => $pemesanan,
            ]);
        }

        $pemesanan->status_pembayaran = $statusPembayaranBaru;
        $pemesanan->save();

        return response()->json([
            'message'   => 'Status pembayaran berhasil diperbarui!',
            'pemesanan' => $pemesanan,
        ]);
    }

    /**
     * Menambahkan suku cadang ke pemesanan.
     */
    public function tambahSukuCadang(Request $request, Booking $pemesanan)
    {
        $dataTervalidasi = $request->validate([
            'id_suku_cadang' => 'required|exists:suku_cadang,id',
            'jumlah'         => 'required|integer|min:1',
        ]);

        $hasil = $this->layananSukuCadang->tambahSukuCadang(
            $pemesanan,
            $dataTervalidasi['id_suku_cadang'],
            $dataTervalidasi['jumlah']
        );

        if (!$hasil['success']) {
            return response()->json([
                'success' => false,
                'message' => $hasil['message'],
            ], 400);
        }

        return response()->json([
            'success'       => true,
            'message'       => $hasil['message'],
            'item_pemesanan' => $hasil['data'],
        ]);
    }

    /**
     * Menghapus suku cadang dari pemesanan.
     */
    public function hapusSukuCadang(Booking $pemesanan, $idItemPemesanan)
    {
        $hasil = $this->layananSukuCadang->hapusSukuCadang($pemesanan, $idItemPemesanan);

        $kodeStatus = $hasil['status_code'] ?? 200;

        return response()->json([
            'success' => $hasil['success'],
            'message' => $hasil['message'],
        ], $kodeStatus);
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia.
     */
    public function daftarSukuCadangTersedia()
    {
        $daftarSukuCadang = $this->layananSukuCadang->ambilSukuCadangTersedia();

        return response()->json([
            'success' => true,
            'data'    => $daftarSukuCadang,
        ]);
    }

    /**
     * Mendapatkan daftar mekanik.
     */
    public function daftarMekanik()
    {
        $daftarMekanik = User::mekanik()
            ->select('id', 'nama', 'email')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $daftarMekanik,
        ]);
    }

    /**
     * Menugaskan mekanik ke pemesanan.
     */
    public function tugaskanMekanik(Request $request, Booking $pemesanan)
    {
        $dataTervalidasi = $request->validate([
            'id_mekanik' => 'nullable|exists:pengguna,id',
        ]);

        // Verifikasi bahwa pengguna yang dipilih adalah mekanik
        if ($dataTervalidasi['id_mekanik']) {
            $mekanik = User::find($dataTervalidasi['id_mekanik']);
            if ($mekanik->role !== 'mekanik') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna yang dipilih bukan mekanik',
                ], 400);
            }
        }

        $pemesanan->id_mekanik = $dataTervalidasi['id_mekanik'];
        $pemesanan->save();

        // Kirim notifikasi ke mekanik jika ditugaskan
        if ($dataTervalidasi['id_mekanik']) {
            $mekanik = User::find($dataTervalidasi['id_mekanik']);
            $this->layananNotifikasi->notifikasiMekanikDitugaskan($pemesanan, $mekanik);
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Mekanik berhasil ditugaskan',
            'pemesanan' => $pemesanan->load('mekanik'),
        ]);
    }
}