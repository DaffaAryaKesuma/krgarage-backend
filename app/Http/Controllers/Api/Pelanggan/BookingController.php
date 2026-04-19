<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use App\Services\NotificationService;
use App\Mail\BookingConfirmedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    protected $layananNotifikasi;

    public function __construct(NotificationService $layananNotifikasi)
    {
        $this->layananNotifikasi = $layananNotifikasi;
    }

    /**
     * Menampilkan daftar pemesanan milik pelanggan yang sedang login.
     */
    public function index(Request $request)
    {
        $perHalaman = $request->query('per_page', 10);
        $bulan      = $request->query('month');
        $tahun      = $request->query('year');

        $query = $request->user()->pemesanan()->with(['vespa', 'layanan', 'itemPemesanan.sukuCadang']);

        // Filter berdasarkan bulan dan tahun jika tersedia
        if ($bulan && $tahun) {
            $query->whereYear('tanggal_pemesanan', $tahun)
                  ->whereMonth('tanggal_pemesanan', $bulan);
        } elseif ($tahun) {
            $query->whereYear('tanggal_pemesanan', $tahun);
        } elseif ($bulan) {
            $query->whereMonth('tanggal_pemesanan', $bulan);
        }

        $daftarPemesanan = $query->latest()->paginate($perHalaman);

        return response()->json($daftarPemesanan);
    }

    /**
     * Menampilkan detail satu pemesanan milik pelanggan yang sedang login.
     */
    public function show(Request $request, Booking $pemesanan)
    {
        if ($pemesanan->id_pengguna !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk melihat pemesanan ini.',
            ], 403);
        }

        $pemesanan->load([
            'pengguna:id,nama,email',
            'vespa:id,model,plat_nomor,tahun_produksi',
            'layanan:id,nama_layanan,harga',
            'mekanik:id,nama,email',
            'itemPemesanan.sukuCadang:id,nama_suku_cadang,kategori',
        ]);

        return response()->json($pemesanan);
    }

    /**
     * Membuat pemesanan servis baru.
     */
    public function store(Request $request)
    {
        $dataTervalidasi = $request->validate([
            'id_vespa' => [
                'required',
                Rule::exists('vespa', 'id')->where(function ($query) use ($request) {
                    return $query->where('id_pengguna', $request->user()->id);
                }),
            ],
            'service_ids'       => 'required|array',
            'service_ids.*'     => 'exists:layanan,id',
            'tanggal_pemesanan' => 'required|date|after_or_equal:today',
            'jam_pemesanan'     => 'required|string',
            'catatan_pelanggan' => 'nullable|string',
        ]);

        // Validasi: Cek apakah jam sudah dipesan di tanggal yang sama
        $pemesananSudahAda = Booking::where('tanggal_pemesanan', $dataTervalidasi['tanggal_pemesanan'])
            ->where('jam_pemesanan', $dataTervalidasi['jam_pemesanan'])
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->first();

        if ($pemesananSudahAda) {
            return response()->json([
                'message' => 'Jam pemesanan ini sudah dipesan oleh pelanggan lain. Silakan pilih jam lain.',
                'errors'  => [
                    'jam_pemesanan' => ['Jam ini sudah tidak tersedia.'],
                ],
            ], 422);
        }

        $pemesanan = $request->user()->pemesanan()->create([
            'id_vespa'          => $dataTervalidasi['id_vespa'],
            'tanggal_pemesanan' => $dataTervalidasi['tanggal_pemesanan'],
            'jam_pemesanan'     => $dataTervalidasi['jam_pemesanan'],
            'catatan_pelanggan' => $dataTervalidasi['catatan_pelanggan'] ?? null,
            'status'            => Booking::STATUS_PENDING,
            'status_pembayaran' => Booking::PAYMENT_STATUS_UNPAID,
        ]);

        $totalHarga = 0;
        foreach ($dataTervalidasi['service_ids'] as $idLayanan) {
            $layanan = Service::findOrFail($idLayanan);
            $pemesanan->layanan()->attach($idLayanan, [
                'harga_saat_pesan' => $layanan->harga,
            ]);
            $totalHarga += $layanan->harga;
        }

        $pemesanan->update(['total_harga' => $totalHarga]);

        // Kirim notifikasi ke semua admin tentang pemesanan baru
        $this->layananNotifikasi->notifikasiAdminPemesananBaru($pemesanan, $request->user());

        // Kirim email konfirmasi ke pelanggan
        try {
            $pemesanan->load('pengguna', 'vespa', 'layanan');
            Mail::to($request->user()->email)->send(new BookingConfirmedMail($pemesanan));
        } catch (\Exception $e) {
            // Abaikan error agar proses booking tidak gagal jika server email error
            \Log::error('Gagal mengirim email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pemesanan servis berhasil dibuat!',
            'data'    => $pemesanan->load('layanan'),
        ], 201);
    }

    /**
     * Mengecek slot jam yang sudah terisi pada tanggal tertentu.
     */
    public function cekSlot(Request $request)
    {
        $tanggal = $request->query('date');
        if (!$tanggal) return response()->json([]);

        $jamTerpesan = Booking::where('tanggal_pemesanan', $tanggal)
                        ->where('status', '!=', Booking::STATUS_CANCELLED)
                        ->pluck('jam_pemesanan');

        return response()->json($jamTerpesan);
    }

    /**
     * Membatalkan pemesanan oleh pelanggan.
     */
    public function batalkan(Request $request, Booking $pemesanan)
    {
        if ($pemesanan->id_pengguna !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk membatalkan pemesanan ini.',
            ], 403);
        }

        // Hanya bisa dibatalkan jika status masih Pending atau Confirmed
        $statusDiperbolehkan = [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED];
        if (!in_array($pemesanan->status, $statusDiperbolehkan)) {
            return response()->json([
                'message' => 'Pemesanan hanya dapat dibatalkan jika statusnya masih "Pending" atau "Confirmed".',
            ], 400);
        }

        $pemesanan->update(['status' => Booking::STATUS_CANCELLED]);

        // Kirim notifikasi ke semua admin tentang pembatalan
        $daftarAdmin = User::admin()->get();

        foreach ($daftarAdmin as $admin) {
            $this->layananNotifikasi->buatNotifikasi(
                $admin->id,
                Notification::TYPE_BOOKING_CANCELLED,
                'Pemesanan Dibatalkan',
                "Pemesanan #{$pemesanan->kode_pemesanan} dibatalkan oleh pelanggan.",
                $pemesanan->id,
                false
            );
        }

        return response()->json([
            'message' => 'Pemesanan berhasil dibatalkan.',
            'data'    => $pemesanan,
        ], 200);
    }
}