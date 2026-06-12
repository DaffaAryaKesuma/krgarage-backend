<?php

namespace App\Http\Controllers\Api\Admin;

// Controller dasar Laravel.
use App\Http\Controllers\Controller;
// Event realtime untuk refresh frontend.
use App\Events\PemesananBerubah;
// Model pemesanan dan user.
use App\Models\Pemesanan;
use App\Models\User;
// Service untuk notifikasi dan pengelolaan suku cadang pemesanan.
use App\Services\NotifikasiService;
use App\Services\PemesananSukuCadangService;
use App\Services\LogAktivitasService;
// Trait response JSON konsisten.
use App\Traits\ApiResponseTrait;
// Form request validasi endpoint admin.
use App\Http\Requests\Admin\UpdateStatusPemesananRequest;
use App\Http\Requests\Admin\UpdateStatusPembayaranRequest;
use App\Http\Requests\Admin\TambahSukuCadangRequest;
use App\Http\Requests\Admin\TugaskanMekanikRequest;
// Resource response.
use App\Http\Resources\PemesananResource;
use App\Http\Resources\SukuCadangResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPemesananController extends Controller
{
    // Helper successResponse dan errorResponse.
    use ApiResponseTrait;

    // Service notifikasi.
    protected $layananNotifikasi;
    // Service logika suku cadang pada pemesanan.
    protected $layananSukuCadang;
    // Service audit aktivitas pemilik.
    protected $logAktivitas;

    // Dependency injection service dari container Laravel.
    public function __construct(
        NotifikasiService $layananNotifikasi, 
        PemesananSukuCadangService $layananSukuCadang,
        LogAktivitasService $logAktivitas
    ) {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->layananSukuCadang = $layananSukuCadang;
        $this->logAktivitas = $logAktivitas;
    }

    /**
     * Menampilkan semua data pemesanan untuk admin.
     */
    public function index(Request $request)
    {
        try {
            // Jumlah data per halaman dari query string.
            $perHalaman = $request->query('per_page', 10);
            
            // Admin melihat semua pemesanan beserta relasi penting.
            $daftarPemesanan = Pemesanan::with(['pengguna', 'vespa', 'layanan', 'mekanik'])
                                ->latest()
                                ->paginate($perHalaman);

            // Resource collection otomatis membawa metadata pagination.
            return PemesananResource::collection($daftarPemesanan);
        } catch (\Exception $e) {
            Log::error('AdminPemesananController@index: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar pemesanan', 500, $e);
        }
    }

    /**
     * Menampilkan detail satu pemesanan.
     */
    public function show(Pemesanan $pemesanan)
    {
        try {
            // Ambil detail pemesanan lengkap lewat service agar relasi konsisten.
            $pemesanan = $this->layananSukuCadang->ambilPemesananDenganRelasi($pemesanan);
            
            return $this->successResponse(
                'Detail pemesanan berhasil dimuat',
                new PemesananResource($pemesanan)
            );
        } catch (\Exception $e) {
            Log::error('AdminPemesananController@show: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat detail pemesanan', 500, $e);
        }
    }

    /**
     * Memperbarui status pemesanan.
     */
    public function updateStatus(UpdateStatusPemesananRequest $request, Pemesanan $pemesanan)
    {
        try {
            // Status baru sudah divalidasi oleh UpdateStatusPemesananRequest.
            $statusBaru = $request->validated('status');
            // Status lama disimpan untuk validasi transisi dan efek samping.
            $statusLama = $pemesanan->status;

            // Jika status tidak berubah, langsung kembalikan response sukses.
            if ($statusLama === $statusBaru) {
                return $this->successResponse(
                    'Status pemesanan sudah berada pada nilai yang sama.', 
                    new PemesananResource($pemesanan)
                );
            }

            // Status final tidak boleh diubah lagi.
            if (in_array($statusLama, [Pemesanan::STATUS_SELESAI, Pemesanan::STATUS_BATAL], true)) {
                return $this->errorResponse('Status pemesanan yang selesai atau dibatalkan tidak dapat diubah.', 400);
            }

            // Daftar transisi status yang diperbolehkan.
            $transisiValid = [
                Pemesanan::STATUS_MENUNGGU => [
                    Pemesanan::STATUS_DIKONFIRMASI,
                    Pemesanan::STATUS_DIKERJAKAN,
                    Pemesanan::STATUS_SELESAI,
                    Pemesanan::STATUS_BATAL,
                ],
                Pemesanan::STATUS_DIKONFIRMASI => [
                    Pemesanan::STATUS_DIKERJAKAN,
                    Pemesanan::STATUS_SELESAI,
                    Pemesanan::STATUS_BATAL,
                ],
                Pemesanan::STATUS_DIKERJAKAN => [
                    Pemesanan::STATUS_SELESAI,
                    Pemesanan::STATUS_BATAL,
                ],
            ];

            // Tolak transisi yang tidak sesuai alur bisnis.
            if (!isset($transisiValid[$statusLama]) || !in_array($statusBaru, $transisiValid[$statusLama], true)) {
                return $this->errorResponse("Transisi status dari {$statusLama} ke {$statusBaru} tidak diperbolehkan.", 400);
            }

            // Set status baru pada model.
            $pemesanan->status = $statusBaru;
            $completedAtLama = $pemesanan->completed_at;
            $catatanMekanikLama = $pemesanan->catatan_mekanik;

            // Jika servis selesai, simpan waktu selesai.
            if ($statusBaru === Pemesanan::STATUS_SELESAI) {
                // completed_at hanya diisi jika sebelumnya belum ada.
                $pemesanan->completed_at ??= now();

                // Jika belum lunas, pastikan status pembayaran Belum Lunas dan paid_at kosong.
                if ($pemesanan->status_pembayaran !== Pemesanan::PAYMENT_STATUS_PAID) {
                    $pemesanan->status_pembayaran = Pemesanan::PAYMENT_STATUS_UNPAID;
                    $pemesanan->paid_at = null;
                }
            }

            // Catatan mekanik disimpan jika dikirim dari frontend.
            if ($request->has('catatan_mekanik')) {
                $pemesanan->catatan_mekanik = $request->validated('catatan_mekanik');
            }

            // Simpan perubahan status/catatan/timestamp.
            $pemesanan->save();

            $dataSebelum = ['status' => $statusLama];
            $dataSesudah = ['status' => $statusBaru];
            if ($request->has('catatan_mekanik')) {
                $dataSebelum['catatan_mekanik'] = $catatanMekanikLama;
                $dataSesudah['catatan_mekanik'] = $pemesanan->catatan_mekanik;
            }
            if ($statusBaru === Pemesanan::STATUS_SELESAI) {
                $dataSebelum['completed_at'] = $completedAtLama;
                $dataSesudah['completed_at'] = $pemesanan->completed_at;
            }

            $this->jalankanEfekSampingAman('audit status pemesanan', function () use ($request, $pemesanan, $statusLama, $statusBaru, $dataSebelum, $dataSesudah) {
                $this->logAktivitas->catat(
                    $request->user(),
                    'edit',
                    'pemesanan',
                    'pemesanan',
                    $pemesanan->id,
                    $pemesanan->kode_pemesanan,
                    "Mengubah status pemesanan #{$pemesanan->kode_pemesanan} dari {$statusLama} menjadi {$statusBaru}",
                    $dataSebelum,
                    $dataSesudah
                );
            });

            // Jalankan efek samping status: notifikasi, pengurangan stok, update tanggal servis.
            $this->handleStatusTransitionEffects($pemesanan, $statusLama, $statusBaru, $request->user());

            // Broadcast realtime agar frontend role lain ikut refresh.
            $this->jalankanEfekSampingAman('broadcast status pemesanan', function () use ($pemesanan) {
                broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'status_updated'));
            });

            return $this->successResponse(
                'Status pemesanan berhasil diperbarui!',
                new PemesananResource($pemesanan->fresh())
            );

        } catch (\Exception $e) {
            Log::error('AdminPemesananController@updateStatus: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui status pemesanan', 500, $e);
        }
    }

    /**
     * Memperbarui status pembayaran pemesanan.
     */
    public function updatePaymentStatus(UpdateStatusPembayaranRequest $request, Pemesanan $pemesanan)
    {
        try {
            // Pembayaran hanya boleh diubah setelah servis selesai.
            if ($pemesanan->status !== Pemesanan::STATUS_SELESAI) {
                return $this->errorResponse('Status pembayaran hanya dapat diubah setelah servis selesai.', 400);
            }

            // Status pembayaran baru sudah divalidasi oleh request.
            $statusPembayaranBaru = $request->validated('status_pembayaran');
            $statusPembayaranLama = $pemesanan->status_pembayaran;
            $paidAtLama = $pemesanan->paid_at;

            // Hindari update berulang jika status sama.
            if ($statusPembayaranLama === $statusPembayaranBaru) {
                return $this->successResponse(
                    'Status pembayaran sudah berada pada nilai yang sama.', 
                    new PemesananResource($pemesanan)
                );
            }

            // Simpan status pembayaran dan timestamp lunas.
            $pemesanan->status_pembayaran = $statusPembayaranBaru;
            // paid_at hanya terisi saat status pembayaran Lunas.
            $pemesanan->paid_at = $statusPembayaranBaru === Pemesanan::PAYMENT_STATUS_PAID
                ? now()
                : null;
            $pemesanan->save();

            $this->logAktivitas->catat(
                $request->user(),
                'edit',
                'keuangan',
                'pemesanan',
                $pemesanan->id,
                $pemesanan->kode_pemesanan,
                "Mengubah status pembayaran pemesanan #{$pemesanan->kode_pemesanan} dari {$statusPembayaranLama} menjadi {$statusPembayaranBaru}",
                [
                    'status_pembayaran' => $statusPembayaranLama,
                    'paid_at' => $paidAtLama,
                ],
                [
                    'status_pembayaran' => $pemesanan->status_pembayaran,
                    'paid_at' => $pemesanan->paid_at,
                ]
            );

            // Jika lunas, beri notifikasi ke pemilik.
            if ($statusPembayaranBaru === Pemesanan::PAYMENT_STATUS_PAID) {
                $this->layananNotifikasi->notifikasiPelangganPembayaranLunas($pemesanan);
                $this->layananNotifikasi->notifikasiPemilikPembayaranDiterima($pemesanan);
            }
            // Broadcast perubahan pembayaran.
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'payment_updated'));

            return $this->successResponse(
                'Status pembayaran berhasil diperbarui!',
                new PemesananResource($pemesanan)
            );

        } catch (\Exception $e) {
            Log::error('AdminPemesananController@updatePaymentStatus: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui status pembayaran', 500, $e);
        }
    }

    /**
     * Menambahkan suku cadang ke pemesanan.
     */
    public function tambahSukuCadang(TambahSukuCadangRequest $request, Pemesanan $pemesanan)
    {
        try {
            // Logika tambah item dipindah ke service agar bisa dipakai admin dan mekanik.
            $hasil = $this->layananSukuCadang->tambahSukuCadang(
                $pemesanan,
                $request->validated('id_suku_cadang'),
                $request->validated('jumlah')
            );

            if (!$hasil['success']) {
                return $this->errorResponse($hasil['message'], 400);
            }

            $itemPemesanan = $hasil['data'];
            $this->logAktivitas->catat(
                $request->user(),
                'tambah',
                'pemesanan',
                'item_pemesanan',
                $itemPemesanan->id,
                $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                "Menambahkan suku cadang ke pemesanan #{$pemesanan->kode_pemesanan}",
                null,
                [
                    'kode_pemesanan' => $pemesanan->kode_pemesanan,
                    'id_suku_cadang' => $itemPemesanan->id_suku_cadang,
                    'nama_suku_cadang' => $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                    'jumlah' => $request->validated('jumlah'),
                    'harga_saat_ini' => $itemPemesanan->harga_saat_ini,
                ]
            );

            // Broadcast agar total/detail pemesanan di frontend ikut berubah.
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'item_added'));

            return $this->successResponse($hasil['message'], [
                'item_pemesanan' => $hasil['data']
            ]);

        } catch (\Exception $e) {
            Log::error('AdminPemesananController@tambahSukuCadang: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan suku cadang', 500, $e);
        }
    }

    /**
     * Menghapus suku cadang dari pemesanan.
     */
    public function hapusSukuCadang(Request $request, Pemesanan $pemesanan, $idItemPemesanan)
    {
        try {
            $itemPemesanan = $pemesanan->itemPemesanan()->with('sukuCadang')->find($idItemPemesanan);
            $dataItemSebelum = $itemPemesanan ? [
                'kode_pemesanan' => $pemesanan->kode_pemesanan,
                'id_suku_cadang' => $itemPemesanan->id_suku_cadang,
                'nama_suku_cadang' => $itemPemesanan->nama_suku_cadang_saat_ini ?? $itemPemesanan->sukuCadang?->nama_suku_cadang,
                'jumlah' => $itemPemesanan->jumlah,
                'harga_saat_ini' => $itemPemesanan->harga_saat_ini,
            ] : null;

            // Service memastikan item milik pemesanan ini dan status masih boleh dihapus.
            $hasil = $this->layananSukuCadang->hapusSukuCadang($pemesanan, $idItemPemesanan);

            if (!$hasil['success']) {
                return $this->errorResponse($hasil['message'], $hasil['status_code'] ?? 400);
            }

            $this->logAktivitas->catat(
                $request->user(),
                'hapus',
                'pemesanan',
                'item_pemesanan',
                (int) $idItemPemesanan,
                $dataItemSebelum['nama_suku_cadang'] ?? null,
                "Menghapus suku cadang dari pemesanan #{$pemesanan->kode_pemesanan}",
                $dataItemSebelum,
                null
            );

            // Broadcast setelah item berhasil dihapus.
            broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'item_deleted'));

            return $this->successResponse($hasil['message']);
            
        } catch (\Exception $e) {
            Log::error('AdminPemesananController@hapusSukuCadang: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus suku cadang', 500, $e);
        }
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia.
     */
    public function daftarSukuCadangTersedia()
    {
        try {
            // Ambil suku cadang dengan stok > 0.
            $daftarSukuCadang = $this->layananSukuCadang->ambilSukuCadangTersedia();
            
            return $this->successResponse(
                'Daftar suku cadang tersedia berhasil dimuat',
                SukuCadangResource::collection($daftarSukuCadang)
            );
        } catch (\Exception $e) {
            Log::error('AdminPemesananController@daftarSukuCadangTersedia: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar suku cadang tersedia', 500, $e);
        }
    }

    /**
     * Mendapatkan daftar mekanik.
     */
    public function daftarMekanik()
    {
        try {
            // Ambil user dengan role mekanik untuk dropdown assign.
            $daftarMekanik = User::mekanik()
                ->select('id', 'nama', 'email')
                ->orderBy('nama')
                ->get();
                
            return $this->successResponse('Daftar mekanik berhasil dimuat', $daftarMekanik);
        } catch (\Exception $e) {
            Log::error('AdminPemesananController@daftarMekanik: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar mekanik', 500, $e);
        }
    }

    /**
     * Menugaskan mekanik ke pemesanan.
     */
    public function tugaskanMekanik(TugaskanMekanikRequest $request, Pemesanan $pemesanan)
    {
        try {
            // id_mekanik sudah divalidasi ada/tidak oleh TugaskanMekanikRequest.
            $idMekanik = $request->validated('id_mekanik');
            $mekanik = null;

            if ($idMekanik) {
                // Pastikan user yang dipilih benar-benar mekanik.
                $mekanik = User::find($idMekanik);
                if (strtolower((string) $mekanik->role) !== 'mekanik') {
                    return $this->errorResponse('Pengguna yang dipilih bukan mekanik', 400);
                }
            }

            $idMekanikLama = $pemesanan->id_mekanik;
            $mekanikLama = $idMekanikLama ? User::find($idMekanikLama) : null;

            // Simpan mekanik ke pemesanan.
            $pemesanan->id_mekanik = $idMekanik;
            $pemesanan->save();

            $this->jalankanEfekSampingAman('audit penugasan mekanik', function () use ($request, $pemesanan, $idMekanik, $idMekanikLama, $mekanikLama, $mekanik) {
                $this->logAktivitas->catat(
                    $request->user(),
                    'edit',
                    'pemesanan',
                    'pemesanan',
                    $pemesanan->id,
                    $pemesanan->kode_pemesanan,
                    $idMekanik
                        ? "Menugaskan {$mekanik->nama} ke pemesanan #{$pemesanan->kode_pemesanan}"
                        : "Menghapus mekanik dari pemesanan #{$pemesanan->kode_pemesanan}",
                    [
                        'id_mekanik' => $idMekanikLama,
                        'nama_mekanik' => $mekanikLama?->nama,
                    ],
                    [
                        'id_mekanik' => $pemesanan->id_mekanik,
                        'nama_mekanik' => isset($mekanik) ? $mekanik->nama : null,
                    ]
                );
            });

            // Beri notifikasi ke mekanik jika ada mekanik yang ditugaskan.
            if ($idMekanik) {
                $this->jalankanEfekSampingAman('notifikasi mekanik ditugaskan', function () use ($pemesanan, $mekanik) {
                    $this->layananNotifikasi->notifikasiMekanikDitugaskan($pemesanan, $mekanik);
                });
            }

            // Broadcast perubahan assign mekanik.
            $this->jalankanEfekSampingAman('broadcast penugasan mekanik', function () use ($pemesanan) {
                broadcast(PemesananBerubah::dariPemesanan($pemesanan->fresh(), 'mechanic_assigned'));
            });

            return $this->successResponse(
                'Mekanik berhasil ditugaskan', 
                new PemesananResource($pemesanan->fresh('mekanik'))
            );

        } catch (\Exception $e) {
            Log::error('AdminPemesananController@tugaskanMekanik: ' . $e->getMessage());
            return $this->errorResponse('Gagal menugaskan mekanik', 500, $e);
        }
    }

    /**
     * Ekstraksi logic transisi status untuk Clean Code.
     */
    private function handleStatusTransitionEffects(Pemesanan $pemesanan, $statusLama, $statusBaru, ?User $aktor)
    {
        // Saat status menjadi selesai, stok dikurangi dan pelanggan diberi notifikasi.
        if ($statusBaru === Pemesanan::STATUS_SELESAI && $statusLama !== Pemesanan::STATUS_SELESAI) {
            // Kurangi stok semua suku cadang yang dipakai pada pemesanan.
            $ringkasanPerubahanStok = $this->layananSukuCadang->kurangiStokSukuCadang($pemesanan);
            $this->jalankanEfekSampingAman('audit pengurangan stok pemesanan selesai', function () use ($pemesanan, $ringkasanPerubahanStok, $aktor) {
                $this->catatLogPenguranganStokSelesai($pemesanan, $ringkasanPerubahanStok, $aktor);
            });

            // Jika stok melewati batas minimal, beri notifikasi ke pemilik.
            foreach ($ringkasanPerubahanStok as $perubahanStok) {
                $this->jalankanEfekSampingAman('notifikasi stok menipis setelah pemesanan selesai', function () use ($perubahanStok) {
                    $this->layananNotifikasi->notifikasiPemilikStokMenipis(
                        $perubahanStok['suku_cadang'],
                        $perubahanStok['stok_sebelum']
                    );
                });
            }

            // Update tanggal servis terakhir/berikutnya pada Vespa pelanggan.
            if ($pemesanan->vespa) {
                $pemesanan->vespa->perbaruiTanggalServisDariPemesanan($pemesanan);
            }

            // Beri notifikasi servis selesai ke pelanggan/admin.
            $this->jalankanEfekSampingAman('notifikasi pemesanan selesai', function () use ($pemesanan) {
                $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);
            });

        } elseif ($statusBaru === Pemesanan::STATUS_DIKONFIRMASI && $statusLama === Pemesanan::STATUS_MENUNGGU) {
            // Notifikasi saat pemesanan dikonfirmasi.
            $this->jalankanEfekSampingAman('notifikasi pemesanan dikonfirmasi', function () use ($pemesanan) {
                $this->layananNotifikasi->notifikasiPemesananDikonfirmasi($pemesanan);
            });
            
        } elseif ($statusBaru === Pemesanan::STATUS_DIKERJAKAN && in_array($statusLama, [Pemesanan::STATUS_DIKONFIRMASI, Pemesanan::STATUS_MENUNGGU])) {
            // Notifikasi saat servis mulai dikerjakan.
            $this->jalankanEfekSampingAman('notifikasi pemesanan diproses', function () use ($pemesanan) {
                $this->layananNotifikasi->notifikasiPemesananDiproses($pemesanan);
            });
            
        } elseif ($statusBaru === Pemesanan::STATUS_BATAL) {
            // Notifikasi saat pemesanan dibatalkan.
            $this->jalankanEfekSampingAman('notifikasi pemesanan dibatalkan', function () use ($pemesanan) {
                $this->layananNotifikasi->notifikasiPemesananDibatalkan($pemesanan);
            });
        }
    }

    /**
     * Efek samping seperti notifikasi, audit, dan broadcast tidak boleh
     * menggagalkan update status utama jika konfigurasi eksternal bermasalah.
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
}
