<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SukuCadang;
use App\Services\NotifikasiService;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Admin\SimpanSukuCadangRequest;
use App\Http\Requests\Admin\UpdateSukuCadangRequest;
use App\Http\Requests\Admin\TambahStokRequest;
use App\Http\Resources\SukuCadangResource;
use App\Models\RiwayatStokSukuCadang;
use App\Services\LogAktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminSukuCadangController extends Controller
{
    use ApiResponseTrait;

    protected $layananNotifikasi;
    protected $logAktivitas;

    public function __construct(
        NotifikasiService $layananNotifikasi,
        LogAktivitasService $logAktivitas,
    )
    {
        $this->layananNotifikasi = $layananNotifikasi;
        $this->logAktivitas = $logAktivitas;
    }

    /**
     * Menampilkan daftar suku cadang dengan filter opsional.
     */
    public function index(Request $request)
    {
        try {
            $query = SukuCadang::with('kategori');

            // Filter berdasarkan kategori
            if ($request->has('id_kategori')) {
                $query->where('id_kategori', $request->id_kategori);
            }

            // Filter stok menipis
            if ($request->has('low_stock') && $request->low_stock == 1) {
                $query->whereColumn('jumlah_stok', '<=', 'batas_minimal_stok');
            }

            // Cari berdasarkan nama
            if ($request->has('search')) {
                $query->where('nama_suku_cadang', 'like', '%' . $request->search . '%');
            }

            $daftarSukuCadang = $query->orderBy('nama_suku_cadang', 'asc')->get();

            return $this->successResponse(
                'Daftar suku cadang berhasil dimuat',
                SukuCadangResource::collection($daftarSukuCadang)
            );

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@index: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar suku cadang', 500, $e);
        }
    }

    /**
     * Menyimpan suku cadang baru.
     */
    public function store(SimpanSukuCadangRequest $request)
    {
        try {
            $sukuCadang = SukuCadang::create($request->validated());

            $this->layananNotifikasi->notifikasiPemilikStokMenipis($sukuCadang->fresh());
            $this->logAktivitas->catat(
                $request->user(),
                'tambah',
                'inventaris',
                'suku_cadang',
                $sukuCadang->id,
                $sukuCadang->nama_suku_cadang,
                "Menambahkan suku cadang {$sukuCadang->nama_suku_cadang}.",
                null,
                $sukuCadang->only([
                    'nama_suku_cadang',
                    'id_kategori',
                    'jumlah_stok',
                    'harga_beli',
                    'harga_jual',
                    'batas_minimal_stok',
                    'deskripsi',
                ]),
            );

            return $this->successResponse(
                'Suku cadang berhasil ditambahkan',
                new SukuCadangResource($sukuCadang),
                201
            );

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@store: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan suku cadang', 500, $e);
        }
    }

    /**
     * Menampilkan detail satu suku cadang.
     */
    public function show(SukuCadang $sukuCadang)
    {
        try {
            $sukuCadang->load('kategori');
            return $this->successResponse(
                'Detail suku cadang berhasil dimuat',
                new SukuCadangResource($sukuCadang)
            );
        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@show: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat detail suku cadang', 500, $e);
        }
    }

    /**
     * Memperbarui data suku cadang.
     */
    public function update(UpdateSukuCadangRequest $request, SukuCadang $sukuCadang)
    {
        try {
            $batasMinimalSebelum = (int) $sukuCadang->batas_minimal_stok;
            $dataSebelum = $sukuCadang->only(array_keys($request->validated()));

            $sukuCadang->update($request->validated());
            $sukuCadang = $sukuCadang->fresh();
            $dataSesudah = $sukuCadang->only(array_keys($request->validated()));
            $perubahan = $this->logAktivitas->perubahan($dataSebelum, $dataSesudah);

            if (
                $request->has('batas_minimal_stok') &&
                $batasMinimalSebelum !== (int) $sukuCadang->batas_minimal_stok
            ) {
                $this->layananNotifikasi->notifikasiPemilikStokMenipis($sukuCadang);
            }

            if (!empty($perubahan['sesudah'])) {
                $this->logAktivitas->catat(
                    $request->user(),
                    'edit',
                    'inventaris',
                    'suku_cadang',
                    $sukuCadang->id,
                    $sukuCadang->nama_suku_cadang,
                    "Mengubah data suku cadang {$sukuCadang->nama_suku_cadang}.",
                    $perubahan['sebelum'],
                    $perubahan['sesudah'],
                );
            }

            return $this->successResponse(
                'Suku cadang berhasil diperbarui',
                new SukuCadangResource($sukuCadang)
            );

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@update: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui suku cadang', 500, $e);
        }
    }

    /**
     * Menghapus suku cadang.
     */
    public function destroy(SukuCadang $sukuCadang)
    {
        try {
            $dataSebelum = $sukuCadang->only([
                'nama_suku_cadang',
                'id_kategori',
                'jumlah_stok',
                'harga_beli',
                'harga_jual',
                'batas_minimal_stok',
                'deskripsi',
            ]);
            $targetId = $sukuCadang->id;
            $targetLabel = $sukuCadang->nama_suku_cadang;

            // Soft delete: keluarkan dari inventaris aktif tanpa memutus item dan laporan transaksi lama.
            $sukuCadang->delete();
            $this->logAktivitas->catat(
                request()->user(),
                'hapus',
                'inventaris',
                'suku_cadang',
                $targetId,
                $targetLabel,
                "Menghapus suku cadang {$targetLabel}.",
                $dataSebelum,
                null,
            );

            return $this->successResponse('Suku cadang berhasil dihapus');

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@destroy: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus suku cadang', 500, $e);
        }
    }

    /**
     * Menambah stok suku cadang.
     */
    public function tambahStok(TambahStokRequest $request, SukuCadang $sukuCadang)
    {
        $pathFotoStruk = null;

        try {
            $data = $request->validated();
            $jumlah = (int) $data['jumlah'];
            $hargaBeliSatuan = (int) $data['harga_beli_satuan'];
            $stokSebelumLog = (int) $sukuCadang->jumlah_stok;
            $hargaBeliSebelumLog = (int) $sukuCadang->harga_beli;

            if ($request->hasFile('foto_struk')) {
                $pathFotoStruk = $request->file('foto_struk')->store('struk-restok', 'public');
            }

            DB::transaction(function () use ($request, $sukuCadang, $data, $jumlah, $hargaBeliSatuan, $pathFotoStruk) {
                $stokSebelum = (int) $sukuCadang->jumlah_stok;
                $stokSesudah = $stokSebelum + $jumlah;

                $sukuCadang->jumlah_stok = $stokSesudah;

                if ($request->boolean('update_harga_beli')) {
                    $sukuCadang->harga_beli = $hargaBeliSatuan;
                }

                $sukuCadang->save();

                RiwayatStokSukuCadang::create([
                    'id_suku_cadang' => $sukuCadang->id,
                    'id_admin' => $request->user()?->id,
                    'jumlah' => $jumlah,
                    'harga_beli_satuan' => $hargaBeliSatuan,
                    'total_pengeluaran' => $jumlah * $hargaBeliSatuan,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'catatan' => $data['catatan'] ?? null,
                    'foto_struk' => $pathFotoStruk,
                ]);
            });

            $sukuCadangTerbaru = $sukuCadang->fresh('kategori');
            $this->logAktivitas->catat(
                $request->user(),
                'restok',
                'inventaris',
                'suku_cadang',
                $sukuCadangTerbaru->id,
                $sukuCadangTerbaru->nama_suku_cadang,
                "Menambah stok {$sukuCadangTerbaru->nama_suku_cadang} sebanyak {$jumlah}.",
                [
                    'jumlah_stok' => $stokSebelumLog,
                    'harga_beli' => $hargaBeliSebelumLog,
                ],
                [
                    'jumlah_stok' => (int) $sukuCadangTerbaru->jumlah_stok,
                    'harga_beli' => (int) $sukuCadangTerbaru->harga_beli,
                    'harga_beli_satuan_restok' => $hargaBeliSatuan,
                    'total_pengeluaran' => $jumlah * $hargaBeliSatuan,
                    'foto_struk' => $pathFotoStruk,
                ],
            );

            return $this->successResponse(
                'Stok suku cadang berhasil ditambah',
                new SukuCadangResource($sukuCadangTerbaru)
            );

        } catch (\Exception $e) {
            if ($pathFotoStruk) {
                Storage::disk('public')->delete($pathFotoStruk);
            }

            Log::error('AdminSukuCadangController@tambahStok: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambah stok suku cadang', 500, $e);
        }
    }

    /**
     * Mendapatkan daftar suku cadang dengan stok menipis.
     */
    public function peringatanStokMenipis()
    {
        try {
            $sukuCadangStokMenipis = SukuCadang::with('kategori')
                ->stokMenipis()
                ->orderBy('jumlah_stok', 'asc')
                ->get();

            return $this->successResponse(
                'Daftar suku cadang stok menipis berhasil dimuat',
                SukuCadangResource::collection($sukuCadangStokMenipis),
                200,
                ['jumlah' => $sukuCadangStokMenipis->count()]
            );

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@peringatanStokMenipis: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat suku cadang stok menipis', 500, $e);
        }
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia (stok > 0).
     */
    public function daftarSukuCadangTersedia()
    {
        try {
            $daftarSukuCadang = SukuCadang::with('kategori')
                ->tersedia()
                ->orderBy('nama_suku_cadang', 'asc')
                ->get();

            return $this->successResponse(
                'Daftar suku cadang tersedia berhasil dimuat',
                SukuCadangResource::collection($daftarSukuCadang)
            );

        } catch (\Exception $e) {
            Log::error('AdminSukuCadangController@daftarSukuCadangTersedia: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar suku cadang tersedia', 500, $e);
        }
    }
}
