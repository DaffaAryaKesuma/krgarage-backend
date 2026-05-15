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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSukuCadangController extends Controller
{
    use ApiResponseTrait;

    protected $layananNotifikasi;

    public function __construct(NotifikasiService $layananNotifikasi)
    {
        $this->layananNotifikasi = $layananNotifikasi;
    }

    /**
     * Menampilkan daftar suku cadang dengan filter opsional.
     */
    public function index(Request $request)
    {
        try {
            $query = SukuCadang::query();

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

            $sukuCadang->update($request->validated());
            $sukuCadang = $sukuCadang->fresh();

            if (
                $request->has('batas_minimal_stok') &&
                $batasMinimalSebelum !== (int) $sukuCadang->batas_minimal_stok
            ) {
                $this->layananNotifikasi->notifikasiPemilikStokMenipis($sukuCadang);
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
            // Cek apakah suku cadang sudah pernah digunakan di pemesanan
            if ($sukuCadang->itemPemesanan()->count() > 0) {
                return $this->errorResponse('Tidak dapat menghapus suku cadang yang sudah digunakan di pemesanan', 400);
            }

            $sukuCadang->delete();

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
        try {
            $sukuCadang->jumlah_stok += $request->validated('jumlah');
            $sukuCadang->save();

            return $this->successResponse(
                'Stok suku cadang berhasil ditambah',
                new SukuCadangResource($sukuCadang)
            );

        } catch (\Exception $e) {
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
            $sukuCadangStokMenipis = SukuCadang::stokMenipis()
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
            $daftarSukuCadang = SukuCadang::tersedia()
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

