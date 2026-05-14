<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pemesanan;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Services\PemesananSukuCadangService;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Admin\UpdateStatusPemesananRequest;
use App\Http\Requests\Admin\UpdateStatusPembayaranRequest;
use App\Http\Requests\Admin\TambahSukuCadangRequest;
use App\Http\Requests\Admin\TugaskanMekanikRequest;
use App\Http\Resources\PemesananResource;
use App\Http\Resources\SukuCadangResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPemesananController extends Controller
{
    use ApiResponseTrait;

    protected $layananNotifikasi;
    protected $layananSukuCadang;

    public function __construct(
        NotifikasiService $layananNotifikasi, 
        PemesananSukuCadangService $layananSukuCadang
    ) {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->layananSukuCadang = $layananSukuCadang;
    }

    /**
     * Menampilkan semua data pemesanan untuk admin.
     */
    public function index(Request $request)
    {
        try {
            $perHalaman = $request->query('per_page', 10);
            
            $daftarPemesanan = Pemesanan::with(['pengguna', 'vespa', 'layanan', 'mekanik'])
                                ->latest()
                                ->paginate($perHalaman);

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
            $statusBaru = $request->validated('status');
            $statusLama = $pemesanan->status;

            if ($statusLama === $statusBaru) {
                return $this->successResponse(
                    'Status pemesanan sudah berada pada nilai yang sama.', 
                    new PemesananResource($pemesanan)
                );
            }

            if (in_array($statusLama, [Pemesanan::STATUS_SELESAI, Pemesanan::STATUS_BATAL], true)) {
                return $this->errorResponse('Status pemesanan yang selesai atau dibatalkan tidak dapat diubah.', 400);
            }

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

            if (!isset($transisiValid[$statusLama]) || !in_array($statusBaru, $transisiValid[$statusLama], true)) {
                return $this->errorResponse("Transisi status dari {$statusLama} ke {$statusBaru} tidak diperbolehkan.", 400);
            }

            $pemesanan->status = $statusBaru;

            if ($statusBaru === Pemesanan::STATUS_SELESAI && $pemesanan->status_pembayaran !== Pemesanan::PAYMENT_STATUS_PAID) {
                $pemesanan->status_pembayaran = Pemesanan::PAYMENT_STATUS_UNPAID;
            }

            if ($request->has('catatan_mekanik')) {
                $pemesanan->catatan_mekanik = $request->validated('catatan_mekanik');
            }

            $pemesanan->save();

            // Handle Notifikasi dan Stok Suku Cadang Service
            $this->handleStatusTransitionEffects($pemesanan, $statusLama, $statusBaru);

            return $this->successResponse(
                'Status pemesanan berhasil diperbarui!',
                new PemesananResource($pemesanan)
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
            if ($pemesanan->status !== Pemesanan::STATUS_SELESAI) {
                return $this->errorResponse('Status pembayaran hanya dapat diubah setelah servis selesai.', 400);
            }

            $statusPembayaranBaru = $request->validated('status_pembayaran');

            if ($pemesanan->status_pembayaran === $statusPembayaranBaru) {
                return $this->successResponse(
                    'Status pembayaran sudah berada pada nilai yang sama.', 
                    new PemesananResource($pemesanan)
                );
            }

            $pemesanan->status_pembayaran = $statusPembayaranBaru;
            $pemesanan->save();

            if ($statusPembayaranBaru === Pemesanan::PAYMENT_STATUS_PAID) {
                $this->layananNotifikasi->notifikasiPemilikPembayaranDiterima($pemesanan);
            }

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
            $hasil = $this->layananSukuCadang->tambahSukuCadang(
                $pemesanan,
                $request->validated('id_suku_cadang'),
                $request->validated('jumlah')
            );

            if (!$hasil['success']) {
                return $this->errorResponse($hasil['message'], 400);
            }

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
    public function hapusSukuCadang(Pemesanan $pemesanan, $idItemPemesanan)
    {
        try {
            $hasil = $this->layananSukuCadang->hapusSukuCadang($pemesanan, $idItemPemesanan);

            if (!$hasil['success']) {
                return $this->errorResponse($hasil['message'], $hasil['status_code'] ?? 400);
            }

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
            $idMekanik = $request->validated('id_mekanik');

            if ($idMekanik) {
                $mekanik = User::find($idMekanik);
                if ($mekanik->role !== 'mekanik') {
                    return $this->errorResponse('Pengguna yang dipilih bukan mekanik', 400);
                }
            }

            $pemesanan->id_mekanik = $idMekanik;
            $pemesanan->save();

            if ($idMekanik) {
                $this->layananNotifikasi->notifikasiMekanikDitugaskan($pemesanan, $mekanik);
            }

            return $this->successResponse(
                'Mekanik berhasil ditugaskan', 
                new PemesananResource($pemesanan->load('mekanik'))
            );

        } catch (\Exception $e) {
            Log::error('AdminPemesananController@tugaskanMekanik: ' . $e->getMessage());
            return $this->errorResponse('Gagal menugaskan mekanik', 500, $e);
        }
    }

    /**
     * Ekstraksi logic transisi status untuk Clean Code
     */
    private function handleStatusTransitionEffects(Pemesanan $pemesanan, $statusLama, $statusBaru)
    {
        if ($statusBaru === Pemesanan::STATUS_SELESAI && $statusLama !== Pemesanan::STATUS_SELESAI) {
            $ringkasanPerubahanStok = $this->layananSukuCadang->kurangiStokSukuCadang($pemesanan);

            foreach ($ringkasanPerubahanStok as $perubahanStok) {
                $this->layananNotifikasi->notifikasiPemilikStokMenipis(
                    $perubahanStok['suku_cadang'],
                    $perubahanStok['stok_sebelum']
                );
            }

            if ($pemesanan->vespa) {
                $pemesanan->vespa->perbaruiTanggalServisDariPemesanan($pemesanan);
            }

            $this->layananNotifikasi->notifikasiPemesananSelesai($pemesanan);

        } elseif ($statusBaru === Pemesanan::STATUS_DIKONFIRMASI && $statusLama === Pemesanan::STATUS_MENUNGGU) {
            $this->layananNotifikasi->notifikasiPemesananDikonfirmasi($pemesanan);
            
        } elseif ($statusBaru === Pemesanan::STATUS_DIKERJAKAN && in_array($statusLama, [Pemesanan::STATUS_DIKONFIRMASI, Pemesanan::STATUS_MENUNGGU])) {
            $this->layananNotifikasi->notifikasiPemesananDiproses($pemesanan);
            
        } elseif ($statusBaru === Pemesanan::STATUS_BATAL) {
            $this->layananNotifikasi->notifikasiPemesananDibatalkan($pemesanan);
        }
    }
}

