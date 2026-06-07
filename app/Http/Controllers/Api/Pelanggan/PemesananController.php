<?php

namespace App\Http\Controllers\Api\Pelanggan;

// Controller dasar Laravel.
use App\Http\Controllers\Controller;
// Event realtime untuk memberi tahu frontend bahwa pemesanan berubah.
use App\Events\PemesananBerubah;
// Model utama pemesanan.
use App\Models\Pemesanan;
use App\Models\Notifikasi;
use App\Models\Layanan;
use App\Models\User;
// Service notifikasi agar logic notifikasi tidak penuh di controller.
use App\Services\NotifikasiService;
use App\Services\LogAktivitasAdminService;
// Email konfirmasi pemesanan pelanggan.
use App\Mail\EmailKonfirmasiPemesanan;
// Trait response JSON konsisten.
use App\Traits\ApiResponseTrait;
// Request validasi buat pemesanan.
use App\Http\Requests\Pelanggan\BuatPemesananRequest;
// Resource untuk membentuk response pemesanan.
use App\Http\Resources\PemesananResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PemesananController extends Controller
{
    // Memakai helper successResponse dan errorResponse.
    use ApiResponseTrait;

    // Service notifikasi disimpan sebagai properti controller.
    protected $layananNotifikasi;
    protected $logAktivitas;

    // Dependency injection NotifikasiService dari container Laravel.
    public function __construct(
        NotifikasiService $layananNotifikasi,
        LogAktivitasAdminService $logAktivitas
    )
    {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->logAktivitas = $logAktivitas;
    }

    /**
     * Menampilkan daftar pemesanan milik pelanggan yang sedang login.
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter pagination dan filter dari query string.
            $perHalaman = $request->query('per_page', 10);
            $bulan      = $request->query('month');
            $tahun      = $request->query('year');

            // Query dimulai dari relasi pemesanan milik user login agar data pelanggan lain tidak ikut tampil.
            $query = $request->user()->pemesanan()->with(['vespa', 'layanan', 'itemPemesanan.sukuCadang']);

            // Filter bulan dan tahun bersifat opsional.
            if ($bulan && $tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun)->whereMonth('tanggal_pemesanan', $bulan);
            } elseif ($tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun);
            } elseif ($bulan) {
                $query->whereMonth('tanggal_pemesanan', $bulan);
            }

            // latest mengurutkan data terbaru dulu, paginate membuat response punya metadata halaman.
            $daftarPemesanan = $query->latest()->paginate($perHalaman);

            // Resource collection membentuk JSON yang konsisten untuk frontend.
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
            // Pastikan pelanggan hanya bisa melihat pemesanan miliknya sendiri.
            if ($pemesanan->id_pengguna !== $request->user()->id) {
                return $this->errorResponse('Anda tidak memiliki akses untuk melihat pemesanan ini.', 403);
            }

            // Load relasi yang dibutuhkan halaman detail.
            $pemesanan->load([
                'pengguna:id,nama,email',
                'vespa:id,model,plat_nomor,tahun_produksi',
                'layanan:id,nama_layanan',
                'mekanik:id,nama,email',
                'itemPemesanan.sukuCadang:id,nama_suku_cadang,id_kategori',
                'itemPemesanan.sukuCadang.kategori:id,nama',
            ]);

            // Kirim detail pemesanan lewat resource.
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
            // Data sudah divalidasi oleh BuatPemesananRequest.
            $data = $request->validated();

            // Jumlah mekanik menjadi kapasitas maksimal pemesanan aktif per tanggal.
            $jumlahMekanik = User::mekanik()->count();

            // Hitung mekanik yang sudah ditugaskan pada tanggal tersebut.
            $mekanikSibuk = Pemesanan::where('tanggal_pemesanan', $data['tanggal_pemesanan'])
                ->whereIn('status', [
                    Pemesanan::STATUS_MENUNGGU,
                    Pemesanan::STATUS_DIKONFIRMASI,
                    Pemesanan::STATUS_DIKERJAKAN,
                ])
                ->whereNotNull('id_mekanik')
                ->distinct('id_mekanik')
                ->count('id_mekanik');

            // Pemesanan aktif yang belum punya mekanik tetap dianggap memakai kapasitas.
            $pemesananTanpaMekanik = Pemesanan::where('tanggal_pemesanan', $data['tanggal_pemesanan'])
                ->whereIn('status', [
                    Pemesanan::STATUS_MENUNGGU,
                    Pemesanan::STATUS_DIKONFIRMASI,
                    Pemesanan::STATUS_DIKERJAKAN,
                ])
                ->whereNull('id_mekanik')
                ->count();

            // Total kapasitas yang sudah terpakai pada tanggal terpilih.
            $totalTerpakai = $mekanikSibuk + $pemesananTanpaMekanik;

            // Jika kapasitas penuh, pelanggan harus memilih tanggal lain.
            if ($jumlahMekanik === 0 || $totalTerpakai >= $jumlahMekanik) {
                return $this->errorResponse('Tidak ada mekanik yang tersedia di tanggal ini. Silakan pilih tanggal lain.', 422);
            }

            // Cek apakah Vespa sedang dalam pemesanan aktif.
            $vespaAktif = Pemesanan::where('id_vespa', $data['id_vespa'])
                ->whereIn('status', [
                    Pemesanan::STATUS_MENUNGGU,
                    Pemesanan::STATUS_DIKONFIRMASI,
                    Pemesanan::STATUS_DIKERJAKAN,
                ])
                ->first();

            if ($vespaAktif) {
                return $this->errorResponse(
                    'Vespa ini masih memiliki pemesanan yang sedang aktif. Selesaikan atau batalkan pemesanan sebelumnya terlebih dahulu.',
                    422
                );
            }

            // Buat pemesanan dengan status awal Menunggu dan pembayaran Belum Lunas.
            $pemesanan = $request->user()->pemesanan()->create([
                'id_vespa'          => $data['id_vespa'],
                'tanggal_pemesanan' => $data['tanggal_pemesanan'],
                'jam_pemesanan'     => $data['jam_pemesanan'],
                'catatan_pelanggan' => $data['catatan_pelanggan'] ?? null,
                'status'            => Pemesanan::STATUS_MENUNGGU,
                'status_pembayaran' => Pemesanan::PAYMENT_STATUS_UNPAID,
            ]);

            // Hitung total harga dari semua layanan yang dipilih.
            $totalHarga = 0;
            foreach ($data['id_layanan'] as $idLayanan) {
                // Cari layanan, jika id tidak valid Laravel akan throw 404.
                $layanan = Layanan::findOrFail($idLayanan);
                // Simpan snapshot harga dan nama layanan pada pivot.
                $pemesanan->layanan()->attach($idLayanan, [
                    'harga_saat_pesan' => $layanan->harga,
                    'nama_layanan_saat_ini' => $layanan->nama_layanan,
                ]);
                $totalHarga += $layanan->harga;
            }

            // Simpan total harga awal dari layanan.
            $pemesanan->update(['total_harga' => $totalHarga]);

            // Buat notifikasi untuk admin bahwa ada pemesanan baru.
            $this->layananNotifikasi->notifikasiAdminPemesananBaru($pemesanan, $request->user());

            $this->logAktivitas->catat(
                $request->user(),
                'tambah',
                'pemesanan',
                'pemesanan',
                $pemesanan->id,
                $pemesanan->kode_pemesanan,
                "Pelanggan membuat pemesanan servis #{$pemesanan->kode_pemesanan}.",
                null,
                [
                    'kode_pemesanan' => $pemesanan->kode_pemesanan,
                    'tanggal_pemesanan' => $pemesanan->tanggal_pemesanan,
                    'jam_pemesanan' => $pemesanan->jam_pemesanan,
                    'status' => $pemesanan->status,
                    'total_harga' => $pemesanan->total_harga,
                ]
            );

            // Broadcast realtime agar frontend yang sedang terbuka dapat refresh.
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'created'));

            // Email konfirmasi bersifat tambahan dan dikirim setelah response agar tidak membuat request timeout.
            $emailPelanggan = $request->user()->email;
            $pemesananUntukEmail = $pemesanan->fresh(['pengguna', 'vespa', 'layanan']);

            app()->terminating(function () use ($emailPelanggan, $pemesananUntukEmail) {
                try {
                    Mail::to($emailPelanggan)->send(new EmailKonfirmasiPemesanan($pemesananUntukEmail));
                } catch (\Throwable $e) {
                    Log::error('Gagal mengirim email: ' . $e->getMessage());
                }
            });

            // Kirim response sukses ke frontend.
            return $this->successResponse('Pemesanan servis berhasil dibuat!', new PemesananResource($pemesanan->load('layanan')), 201);

        } catch (\Exception $e) {
            Log::error('Pelanggan/PemesananController@store: ' . $e->getMessage());
            return $this->errorResponse('Gagal membuat pemesanan', 500, $e);
        }
    }

    /**
     * Mengecek slot jam yang sudah terisi penuh pada tanggal tertentu.
     * Slot dianggap penuh jika semua kapasitas mekanik di hari tersebut sudah terisi,
     * baik oleh mekanik yang sudah ditugaskan maupun pemesanan yang belum ada mekaniknya.
     */
    public function cekSlot(Request $request)
    {
        try {
            // Tanggal dikirim dari query parameter date.
            $tanggal = $request->query('date');
            if (!$tanggal) return $this->successResponse('Slot kosong', []);

            // Kapasitas slot berdasarkan jumlah mekanik.
            $jumlahMekanik = User::mekanik()->count();

            // Jika tidak ada mekanik, semua slot dianggap penuh.
            if ($jumlahMekanik === 0) {
                $semuaSlot = ['10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                return $this->successResponse('Tidak ada mekanik tersedia', $semuaSlot);
            }

            // Status yang dianggap masih memakai kapasitas mekanik.
            $statusAktif = [
                Pemesanan::STATUS_MENUNGGU,
                Pemesanan::STATUS_DIKONFIRMASI,
                Pemesanan::STATUS_DIKERJAKAN,
            ];

            // Mekanik yang sudah ditugaskan ke pemesanan aktif hari ini
            $mekanikSibuk = Pemesanan::where('tanggal_pemesanan', $tanggal)
                ->whereIn('status', $statusAktif)
                ->whereNotNull('id_mekanik')
                ->distinct('id_mekanik')
                ->count('id_mekanik');

            // Pemesanan aktif yang belum ada mekaniknya (tetap mengisi kapasitas)
            $pemesananTanpaMekanik = Pemesanan::where('tanggal_pemesanan', $tanggal)
                ->whereIn('status', $statusAktif)
                ->whereNull('id_mekanik')
                ->count();

            // Jika total terpakai sudah sama/melebihi jumlah mekanik, semua jam ditutup.
            $totalTerpakai = $mekanikSibuk + $pemesananTanpaMekanik;

            if ($totalTerpakai >= $jumlahMekanik) {
                $semuaSlot = ['10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                return $this->successResponse('Daftar slot terpesan', $semuaSlot);
            }

            // Jika kapasitas masih tersedia, frontend menerima array kosong.
            return $this->successResponse('Daftar slot terpesan', []);

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
            // Pelanggan hanya boleh membatalkan pemesanan miliknya sendiri.
            if ($pemesanan->id_pengguna !== $request->user()->id) {
                return $this->errorResponse('Anda tidak memiliki akses untuk membatalkan pemesanan ini.', 403);
            }

            // Hanya status awal yang boleh dibatalkan oleh pelanggan.
            $statusDiperbolehkan = [Pemesanan::STATUS_MENUNGGU, Pemesanan::STATUS_DIKONFIRMASI];
            if (!in_array($pemesanan->status, $statusDiperbolehkan)) {
                return $this->errorResponse('Pemesanan hanya dapat dibatalkan jika statusnya masih "Menunggu" atau "Dikonfirmasi".', 400);
            }

            // Update status menjadi Batal.
            $statusLama = $pemesanan->status;
            $pemesanan->update(['status' => Pemesanan::STATUS_BATAL]);

            $this->logAktivitas->catat(
                $request->user(),
                'batal',
                'pemesanan',
                'pemesanan',
                $pemesanan->id,
                $pemesanan->kode_pemesanan,
                "Pelanggan membatalkan pemesanan #{$pemesanan->kode_pemesanan}.",
                ['status' => $statusLama],
                ['status' => Pemesanan::STATUS_BATAL]
            );

            // Broadcast agar halaman admin/pemilik ikut refresh.
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'cancelled'));

            // Beri notifikasi ke semua admin.
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
