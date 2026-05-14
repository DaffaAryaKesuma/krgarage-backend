<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Vespa;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Pelanggan\TambahVespaRequest;
use App\Http\Requests\Pelanggan\UpdateVespaRequest;
use App\Http\Resources\VespaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VespaController extends Controller
{
    use ApiResponseTrait;

    /**
     * Menampilkan daftar vespa milik pengguna yang sedang login.
     */
    public function index(Request $request)
    {
        try {
            $daftarVespa = $request->user()
                ->vespas()
                ->denganTanggalServisTerakhir()
                ->get()
                ->each(function ($vespa) {
                    $vespa->sinkronTanggalServisDariAtribut();
                    $vespa->save();
                });

            return $this->successResponse('Daftar vespa berhasil dimuat', VespaResource::collection($daftarVespa));
        } catch (\Exception $e) {
            Log::error('VespaController@index: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat daftar vespa', 500, $e);
        }
    }

    /**
     * Menyimpan data vespa baru.
     */
    public function store(TambahVespaRequest $request)
    {
        try {
            $vespa = $request->user()->vespas()->create($request->validated());
            return $this->successResponse('Vespa berhasil ditambahkan!', new VespaResource($vespa), 201);
        } catch (\Exception $e) {
            Log::error('VespaController@store: ' . $e->getMessage());
            return $this->errorResponse('Gagal menambahkan vespa', 500, $e);
        }
    }

    /**
     * Memperbarui data vespa.
     */
    public function update(UpdateVespaRequest $request, Vespa $vespa)
    {
        try {
            if ($request->user()->id !== $vespa->id_pengguna) {
                return $this->errorResponse('Tidak memiliki akses untuk mengubah vespa ini', 403);
            }

            $vespa->update($request->validated());
            return $this->successResponse('Data Vespa berhasil diperbarui', new VespaResource($vespa));
        } catch (\Exception $e) {
            Log::error('VespaController@update: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui vespa', 500, $e);
        }
    }

    /**
     * Menghapus data vespa.
     */
    public function destroy(Request $request, Vespa $vespa)
    {
        try {
            if ($request->user()->id !== $vespa->id_pengguna) {
                return $this->errorResponse('Tidak memiliki akses untuk menghapus vespa ini', 403);
            }

            $vespa->delete();
            return $this->successResponse('Vespa berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('VespaController@destroy: ' . $e->getMessage());
            return $this->errorResponse('Gagal menghapus vespa', 500, $e);
        }
    }

    /**
     * Menampilkan daftar vespa milik pengguna yang perlu diservis.
     */
    public function perluServis(Request $request)
    {
        try {
            $daftarVespa = $request->user()
                ->vespas()
                ->denganTanggalServisTerakhir()
                ->get()
                ->each(function ($vespa) {
                    $vespa->sinkronTanggalServisDariAtribut();
                    $vespa->save();
                })
                ->filter(function ($vespa) {
                    return $vespa->perlu_servis;
                })
                ->values();

            return $this->successResponse('Daftar vespa perlu servis berhasil dimuat', VespaResource::collection($daftarVespa));
        } catch (\Exception $e) {
            Log::error('VespaController@perluServis: ' . $e->getMessage());
            return $this->errorResponse('Gagal memuat vespa perlu servis', 500, $e);
        }
    }
}
