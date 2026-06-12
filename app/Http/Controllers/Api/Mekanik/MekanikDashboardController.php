<?php

namespace App\Http\Controllers\Api\Mekanik;

use App\Http\Controllers\Controller;
use App\Events\PemesananBerubah;
use App\Models\Pemesanan;
use App\Models\ItemPemesanan;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Services\PemesananSukuCadangService;
use App\Services\LogAktivitasService;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Mekanik\UpdateStatusPemesananMekanikRequest;
use App\Http\Requests\Mekanik\TambahSukuCadangRequest;
use App\Http\Resources\PemesananResource;
use App\Http\Resources\SukuCadangResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MekanikDashboardController extends Controller
{
    use ApiResponseTrait;

    protected $layananNotifikasi;
    protected $layananSukuCadang;
    protected $logAktivitas;

    public function __construct(
        NotifikasiService $layananNotifikasi,
        PemesananSukuCadangService $layananSukuCadang,
        LogAktivitasService $logAktivitas
    )
    {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->layananSukuCadang = $layananSukuCadang;
        $this->logAktivitas = $logAktivitas;
    }

    /**
     * Menampilkan daftar pekerjaan mekanik (pemesanan yang ditugaskan).
     */
    public function index(Request $request)
    {
        try {
            $idMekanik  = $request->user()->id;
            $perHalaman = $request->query('per_page', 10);
            $bulan      = $request->query('month');
            $tahun      = $request->query('year');

            $query = Pemesanan::with(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang'])
                ->where('id_mekanik', $idMekanik);

            if ($request->has('status')) {
                $query->where('status', $request->query('status'));
            }

            if ($bulan && $tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun)->whereMonth('tanggal_pemesanan', $bulan);
            } elseif ($tahun) {
                $query->whereYear('tanggal_pemesanan', $tahun);
            } elseif ($bulan) {
                $query->whereMonth('tanggal_pemesanan', $bulan);
            }

            $daftarPemesanan = $query->orderBy('tanggal_pemesanan', 'desc')
                ->orderBy('jam_pemesanan', 'desc')
                ->paginate($perHalaman);

            return PemesananResource::collection($daftarPemesanan);

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@index: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar pekerjaan', 500, $e);
        }
    }

    /**
     * Menampilkan detail satu pemesanan.
     */
    public function show(Pemesanan $pemesanan)
    {
        try {
            if ($responAkses = $this->pastikanMekanikDitugaskan(auth()->user()->id, $pemesanan)) {
                return $responAkses;
            }

            $pemesanan = $this->layananSukuCadang->ambilPemesananDenganRelasi($pemesanan);

            return $this->successResponse('Detail pemesanan berhasil dimuat', new PemesananResource($pemesanan));

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@show: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat detail pemesanan', 500, $e);
        }
    }

    /**
     * Memperbarui status pemesanan oleh mekanik.
     */
    public function updateStatus(UpdateStatusPemesananMekanikRequest $request, Pemesanan $pemesanan)
    {
        try {
            if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) {
                return $responAkses;
            }

            $data = $request->validated();
            $statusLama = $pemesanan->status;
            $statusBaru = $data['status'];
            $catatanMekanik = trim($data['catatan_mekanik']);
            $catatanMekanikLama = $pemesanan->catatan_mekanik;
            $completedAtLama = $pemesanan->completed_at;

            if ($catatanMekanik === '') {
                return $this->errorResponse('Data tidak valid: Catatan mekanik wajib diisi.', 422);
            }

            if (in_array($statusLama, [Pemesanan::STATUS_SELESAI, Pemesanan::STATUS_BATAL], true)) {
                return $this->errorResponse('Status pemesanan yang dibatalkan atau selesai tidak bisa diubah.', 400);
            }

            if ($statusLama !== Pemesanan::STATUS_DIKERJAKAN) {
                return $this->errorResponse('Mekanik hanya bisa menyelesaikan servis yang statusnya In Progress.', 400);
            }

            $this->handleCompletionEffects($pemesanan, $statusBaru, $request->user());

            $pemesanan->status = $statusBaru;
            if ($statusBaru === Pemesanan::STATUS_SELESAI) {
                $pemesanan->completed_at ??= now();

                if ($pemesanan->status_pembayaran !== Pemesanan::PAYMENT_STATUS_PAID) {
                    $pemesanan->status_pembayaran = Pemesanan::PAYMENT_STATUS_UNPAID;
                    $pemesanan->paid_at = null;
                }
            }

            $pemesanan->catatan_mekanik = $catatanMekanik;
            $pemesanan->save();

            $this->logAktivitas->catat(
                $request->user(),
                'edit',
                'pemesanan',
                'pemesanan',
                $pemesanan->id,
                $pemesanan->kode_pemesanan,
                "Mekanik menyelesaikan servis pemesanan #{$pemesanan->kode_pemesanan}.",
                [
                    'status' => $statusLama,
                    'catatan_mekanik' => $catatanMekanikLama,
                    'completed_at' => $completedAtLama,
                ],
                [
                    'status' => $pemesanan->status,
                    'catatan_mekanik' => $pemesanan->catatan_mekanik,
                    'completed_at' => $pemesanan->completed_at,
                ]
            );

            if ($statusBaru === Pemesanan::STATUS_SELESAI) {
                $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);
            }
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'status_updated'));

            return $this->successResponse(
                'Status pemesanan berhasil diperbarui', 
                new PemesananResource($pemesanan->fresh(['itemPemesanan.sukuCadang']))
            );

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@updateStatus: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui status pemesanan', 500, $e);
        }
    }

    /**
     * Menambahkan suku cadang ke pemesanan.
     */
    public function tambahSukuCadang(TambahSukuCadangRequest $request, Pemesanan $pemesanan)
    {
        try {
            if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) return $responAkses;
            if ($responStatus = $this->pastikanPemesananMasihBisaDimodifikasi($pemesanan)) return $responStatus;

            $data = $request->validated();
            
            $hasil = $this->layananSukuCadang->tambahSukuCadang(
                $pemesanan,
                $data['id_suku_cadang'],
                $data['jumlah']
            );

            if (!$hasil['success']) {
                return $this->errorResponse($hasil['message'], 400);
            }

            $itemPemesanan = $hasil['data'];
            $this->jalankanEfekSampingAman('audit mekanik tambah suku cadang', function () use ($request, $pemesanan, $itemPemesanan, $data) {
                $this->logAktivitas->catat(
                    $request->user(),
                    'tambah',
                    'pemesanan',
                    'item_pemesanan',
                    $itemPemesanan->id,
                    $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                    "Menambahkan suku cadang ke pemesanan #{$pemesanan->kode_pemesanan}.",
                    null,
                    [
                        'kode_pemesanan' => $pemesanan->kode_pemesanan,
                        'id_suku_cadang' => $itemPemesanan->id_suku_cadang,
                        'nama_suku_cadang' => $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                        'jumlah' => $data['jumlah'],
                        'harga_saat_ini' => $itemPemesanan->harga_saat_ini,
                    ]
                );
            });

            $this->jalankanEfekSampingAman('broadcast mekanik tambah suku cadang', function () use ($pemesanan) {
                broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'item_added'));
            });

            return $this->successResponse($hasil['message'], $hasil['data'], 201);

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@tambahSukuCadang: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan suku cadang ke pemesanan', 500, $e);
        }
    }

    /**
     * Menghapus suku cadang dari pemesanan.
     */
    public function hapusSukuCadang(Request $request, Pemesanan $pemesanan, ItemPemesanan $itemPemesanan)
    {
        try {
            if ($responAkses = $this->pastikanMekanikDitugaskan($request->user()->id, $pemesanan)) return $responAkses;
            if ($responStatus = $this->pastikanPemesananMasihBisaDimodifikasi($pemesanan)) return $responStatus;

            if ($itemPemesanan->id_pemesanan !== $pemesanan->id) {
                return $this->errorResponse('Item pemesanan tidak ditemukan.', 404);
            }

            $dataItemSebelum = [
                'kode_pemesanan' => $pemesanan->kode_pemesanan,
                'id_suku_cadang' => $itemPemesanan->id_suku_cadang,
                'nama_suku_cadang' => $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                'jumlah' => $itemPemesanan->jumlah,
                'harga_saat_ini' => $itemPemesanan->harga_saat_ini,
            ];

            $hasil = $this->layananSukuCadang->hapusSukuCadang($pemesanan, $itemPemesanan->id);
            $kodeStatus = $hasil['status_code'] ?? 200;

            if (!$hasil['success']) return $this->errorResponse($hasil['message'], $kodeStatus);

            $this->logAktivitas->catat(
                $request->user(),
                'hapus',
                'pemesanan',
                'item_pemesanan',
                $itemPemesanan->id,
                $dataItemSebelum['nama_suku_cadang'],
                "Menghapus suku cadang dari pemesanan #{$pemesanan->kode_pemesanan}.",
                $dataItemSebelum,
                null
            );

            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'item_deleted'));

            return $this->successResponse($hasil['message']);

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@hapusSukuCadang: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus suku cadang', 500, $e);
        }
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia.
     */
    public function daftarSukuCadangTersedia()
    {
        try {
            $daftarSukuCadang = $this->layananSukuCadang->ambilSukuCadangTersedia();
            return $this->successResponse('Daftar suku cadang berhasil dimuat', SukuCadangResource::collection($daftarSukuCadang));
        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@daftarSukuCadangTersedia: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar suku cadang', 500, $e);
        }
    }

    /**
     * Mendapatkan riwayat pekerjaan mekanik yang sudah selesai.
     */
    public function riwayat(Request $request)
    {
        try {
            $idMekanik = $request->user()->id;

            $query = Pemesanan::with(['pengguna', 'vespa', 'layanan', 'itemPemesanan.sukuCadang'])
                ->where('status', Pemesanan::STATUS_SELESAI)
                ->where('id_mekanik', $idMekanik);

            if ($request->has('days')) {
                $query->where('updated_at', '>=', now()->subDays((int)$request->days));
            }

            $daftarPemesanan = $query->orderBy('updated_at', 'desc')->limit(50)->get();

            return $this->successResponse('Riwayat pekerjaan berhasil dimuat', PemesananResource::collection($daftarPemesanan));

        } catch (\Exception $e) {
            Log::error('MekanikDashboardController@riwayat: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat riwayat pekerjaan', 500, $e);
        }
    }

    private function handleCompletionEffects(Pemesanan $pemesanan, $statusBaru, ?User $aktor)
    {
        if ($statusBaru === Pemesanan::STATUS_SELESAI && $pemesanan->itemPemesanan()->exists()) {
            $ringkasanPerubahanStok = $this->layananSukuCadang->kurangiStokSukuCadang($pemesanan);
            $this->catatLogPenguranganStokSelesai($pemesanan, $ringkasanPerubahanStok, $aktor);
            foreach ($ringkasanPerubahanStok as $perubahan) {
                $this->layananNotifikasi->notifikasiPemilikStokMenipis($perubahan['suku_cadang'], $perubahan['stok_sebelum']);
            }
        }
        if ($statusBaru === Pemesanan::STATUS_SELESAI && $pemesanan->vespa) {
            $pemesanan->vespa->perbaruiTanggalServisDariPemesanan($pemesanan);
        }
    }

    /**
     * Catat audit stok suku cadang yang berkurang saat pemesanan selesai.
     *
     * @param array<int, array{suku_cadang: mixed, stok_sebelum: int, stok_sesudah: int}> $ringkasanPerubahanStok
     */
    private function catatLogPenguranganStokSelesai(Pemesanan $pemesanan, array $ringkasanPerubahanStok, ?User $aktor): void
    {
        foreach ($ringkasanPerubahanStok as $perubahanStok) {
            $sukuCadang = $perubahanStok['suku_cadang'];
            $stokSebelum = (int) $perubahanStok['stok_sebelum'];
            $stokSesudah = (int) $perubahanStok['stok_sesudah'];

            if ($stokSebelum === $stokSesudah) {
                continue;
            }

            $namaSukuCadang = $sukuCadang->nama_suku_cadang ?? 'Suku cadang';

            $this->logAktivitas->catat(
                $aktor,
                'edit',
                'inventaris',
                'suku_cadang',
                $sukuCadang->id ?? null,
                $namaSukuCadang,
                "Booking #{$pemesanan->kode_pemesanan} selesai, stok {$namaSukuCadang} berkurang dari {$stokSebelum} menjadi {$stokSesudah}.",
                ['jumlah_stok' => $stokSebelum],
                ['jumlah_stok' => $stokSesudah]
            );
        }
    }

    /**
     * Efek samping seperti audit dan broadcast tidak boleh menggagalkan aksi utama.
     */
    private function jalankanEfekSampingAman(string $namaAksi, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error("Gagal menjalankan {$namaAksi}: " . $e->getMessage(), [
                'exception' => get_class($e),
            ]);
        }
    }

    private function pastikanMekanikDitugaskan(int $idMekanikLogin, Pemesanan $pemesanan)
    {
        if ((int) $pemesanan->id_mekanik !== $idMekanikLogin) {
            return $this->errorResponse('Anda tidak memiliki akses ke pemesanan ini.', 403);
        }
        return null;
    }

    private function pastikanPemesananMasihBisaDimodifikasi(Pemesanan $pemesanan)
    {
        if (in_array($pemesanan->status, [Pemesanan::STATUS_SELESAI, Pemesanan::STATUS_BATAL], true)) {
            return $this->errorResponse('Pemesanan yang sudah selesai atau dibatalkan tidak dapat dimodifikasi.', 400);
        }
        return null;
    }
}
