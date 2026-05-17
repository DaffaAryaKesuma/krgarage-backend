<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\Notifikasi;
use App\Models\Layanan;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Mail\EmailKonfirmasiPemesanan;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Pelanggan\BuatPemesananRequest;
use App\Http\Resources\PemesananResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PemesananController extends Controller
{
    use ApiResponseTrait;

    protected $layananNotifikasi;

    public function __construct(NotifikasiService $layananNotifikasi)
    {
        $this->layananNotifikasi = $layananNotifikasi;
    }

    /**
     * Menampilkan daftar pemesanan milik pelanggan yang sedang login.
     */
    public function index(Request $request)
    {
        try {
            $perHalaman = $request->query('per_page', 10);
            $bulan      = $request->query('month');
            $tahun      = $request->query('year');

            $query = $request->user()->pemesanan()->with(['vespa', 'layanan', 'itemPemesanan.sukuCadang']);

            if ($bulan && $tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun)->whereMonth('tanggal_pemesanan', $bulan);
            } elseif ($tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun);
            } elseif ($bulan) {
                $query->whereMonth('tanggal_pemesanan', $bulan);
            }

            $daftarPemesanan = $query->latest()->paginate($perHalaman);

            return PemesananResource::collection($daftarPemesanan);

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@index: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar pemesanan', 500, $e);
        }
    }

    /**
     * Menampilkan detail satu pemesanan milik pelanggan yang sedang login.
     */
    public function show(Request $request, Pemesanan $pemesanan)
    {
        try {
            if ($pemesanan->id_pengguna !== $request->user()->id) {
                return $this->errorResponse('Anda tidak memiliki akses untuk melihat pemesanan ini.', 403);
            }

            $pemesanan->load([
                'pengguna:id,nama,email',
                'vespa:id,model,plat_nomor,tahun_produksi',
                'layanan:id,nama_layanan',
                'mekanik:id,nama,email',
                'itemPemesanan.sukuCadang:id,nama_suku_cadang,id_kategori',
                'itemPemesanan.sukuCadang.kategori:id,nama',
            ]);

            return $this->successResponse('Detail pemesanan berhasil dimuat', new PemesananResource($pemesanan));

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@show: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat detail pemesanan', 500, $e);
        }
    }

    /**
     * Membuat pemesanan servis baru.
     */
    public function store(BuatPemesananRequest $request)
    {
        try {
            $data = $request->validated();

            $pemesananSudahAda = Pemesanan::where('tanggal_pemesanan', $data['tanggal_pemesanan'])
                ->where('jam_pemesanan', $data['jam_pemesanan'])
                ->where('status', '!=', Pemesanan::STATUS_BATAL)
                ->first();

            if ($pemesananSudahAda) {
                return $this->errorResponse('Jam pemesanan ini sudah dipesan. Silakan pilih jam lain.', 422);
            }

            $pemesanan = $request->user()->pemesanan()->create([
                'id_vespa'          => $data['id_vespa'],
                'tanggal_pemesanan' => $data['tanggal_pemesanan'],
                'jam_pemesanan'     => $data['jam_pemesanan'],
                'catatan_pelanggan' => $data['catatan_pelanggan'] ?? null,
                'status'            => Pemesanan::STATUS_MENUNGGU,
                'status_pembayaran' => Pemesanan::PAYMENT_STATUS_UNPAID,
            ]);

            $totalHarga = 0;
            foreach ($data['id_layanan'] as $idLayanan) {
                $layanan = Layanan::findOrFail($idLayanan);
                $pemesanan->layanan()->attach($idLayanan, ['harga_saat_pesan' => $layanan->harga]);
                $totalHarga += $layanan->harga;
            }

            $pemesanan->update(['total_harga' => $totalHarga]);

            $this->layananNotifikasi->notifikasiAdminPemesananBaru($pemesanan, $request->user());

            try {
                $pemesanan->load('pengguna', 'vespa', 'layanan');
                Mail::to($request->user()->email)->send(new EmailKonfirmasiPemesanan($pemesanan));
            } catch (\Exception $e) {
                Log::error('Gagal mengirim email: ' . $e->getMessage());
            }

            return $this->successResponse('Pemesanan servis berhasil dibuat!', new PemesananResource($pemesanan->load('layanan')), 201);

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@store: ' . $e->getMessage());
            return $this->errorResponse('Gagal membuat pemesanan', 500, $e);
        }
    }

    /**
     * Mengecek slot jam yang sudah terisi pada tanggal tertentu.
     */
    public function cekSlot(Request $request)
    {
        try {
            $tanggal = $request->query('date');
            if (!$tanggal) return $this->successResponse('Slot kosong', []);

            $jamTerpesan = Pemesanan::where('tanggal_pemesanan', $tanggal)
                            ->where('status', '!=', Pemesanan::STATUS_BATAL)
                            ->pluck('jam_pemesanan');

            return $this->successResponse('Daftar slot terpesan', $jamTerpesan);

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@cekSlot: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat slot', 500, $e);
        }
    }

    /**
     * Membatalkan pemesanan oleh pelanggan.
     */
    public function batalkan(Request $request, Pemesanan $pemesanan)
    {
        try {
            if ($pemesanan->id_pengguna !== $request->user()->id) {
                return $this->errorResponse('Anda tidak memiliki akses untuk membatalkan pemesanan ini.', 403);
            }

            $statusDiperbolehkan = [Pemesanan::STATUS_MENUNGGU, Pemesanan::STATUS_DIKONFIRMASI];
            if (!in_array($pemesanan->status, $statusDiperbolehkan)) {
                return $this->errorResponse('Pemesanan hanya dapat dibatalkan jika statusnya masih "Menunggu" atau "Dikonfirmasi".', 400);
            }

            $pemesanan->update(['status' => Pemesanan::STATUS_BATAL]);

            $daftarAdmin = User::admin()->get();
            foreach ($daftarAdmin as $admin) {
                $this->layananNotifikasi->buatNotifikasi(
                    $admin->id,
                    Notifikasi::TIPE_PEMESANAN_DIBATALKAN,
                    'Pemesanan Dibatalkan',
                    "Pemesanan #{$pemesanan->kode_pemesanan} dibatalkan oleh pelanggan.",
                    $pemesanan->id,
                    false
                );
            }

            return $this->successResponse('Pemesanan berhasil dibatalkan.', new PemesananResource($pemesanan));

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@batalkan: ' . $e->getMessage());
            return $this->errorResponse('Gagal membatalkan pemesanan', 500, $e);
        }
    }
}



