<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sparepart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminSparepartController extends Controller
{
    /**
     * Menampilkan daftar suku cadang dengan filter opsional.
     */
    public function index(Request $request)
    {
        try {
            $query = Sparepart::query();

            // Filter berdasarkan kategori
            if ($request->has('kategori')) {
                $query->where('kategori', $request->kategori);
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

            return response()->json([
                'success' => true,
                'message' => 'Daftar suku cadang berhasil dimuat',
                'data'    => $daftarSukuCadang,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan suku cadang baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_suku_cadang'   => 'required|string|max:255',
            'kategori'           => 'required|string|max:255',
            'jumlah_stok'        => 'required|integer|min:0',
            'harga_beli'         => 'required|numeric|min:0',
            'harga_jual'         => 'required|numeric|min:0',
            'batas_minimal_stok' => 'nullable|integer|min:0',
            'deskripsi'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $sukuCadang = Sparepart::create([
                'nama_suku_cadang'   => $request->nama_suku_cadang,
                'kategori'           => $request->kategori,
                'jumlah_stok'        => $request->jumlah_stok,
                'harga_beli'         => $request->harga_beli,
                'harga_jual'         => $request->harga_jual,
                'batas_minimal_stok' => $request->batas_minimal_stok ?? 5,
                'deskripsi'          => $request->deskripsi,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suku cadang berhasil ditambahkan',
                'data'    => $sukuCadang,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan detail satu suku cadang.
     */
    public function show(Sparepart $sukuCadang)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Detail suku cadang berhasil dimuat',
                'data'    => $sukuCadang,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memperbarui data suku cadang.
     */
    public function update(Request $request, Sparepart $sukuCadang)
    {
        $validator = Validator::make($request->all(), [
            'nama_suku_cadang'   => 'sometimes|string|max:255',
            'kategori'           => 'sometimes|string|max:255',
            'jumlah_stok'        => 'sometimes|integer|min:0',
            'harga_beli'         => 'sometimes|numeric|min:0',
            'harga_jual'         => 'sometimes|numeric|min:0',
            'batas_minimal_stok' => 'sometimes|integer|min:0',
            'deskripsi'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $sukuCadang->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Suku cadang berhasil diperbarui',
                'data'    => $sukuCadang->fresh(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus suku cadang.
     */
    public function destroy(Sparepart $sukuCadang)
    {
        try {
            // Cek apakah suku cadang sudah pernah digunakan di pemesanan
            if ($sukuCadang->itemPemesanan()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus suku cadang yang sudah digunakan di pemesanan',
                ], 400);
            }

            $sukuCadang->delete();

            return response()->json([
                'success' => true,
                'message' => 'Suku cadang berhasil dihapus',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menambah stok suku cadang.
     */
    public function tambahStok(Request $request, Sparepart $sukuCadang)
    {
        $validator = Validator::make($request->all(), [
            'jumlah' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $sukuCadang->jumlah_stok += $request->jumlah;
            $sukuCadang->save();

            return response()->json([
                'success' => true,
                'message' => 'Stok suku cadang berhasil ditambah',
                'data'    => $sukuCadang,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah stok suku cadang',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar suku cadang dengan stok menipis.
     */
    public function peringatanStokMenipis()
    {
        try {
            $sukuCadangStokMenipis = Sparepart::stokMenipis()
                ->orderBy('jumlah_stok', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar suku cadang stok menipis berhasil dimuat',
                'data'    => $sukuCadangStokMenipis,
                'jumlah'  => $sukuCadangStokMenipis->count(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat suku cadang stok menipis',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan daftar suku cadang yang tersedia (stok > 0).
     */
    public function daftarSukuCadangTersedia()
    {
        try {
            $daftarSukuCadang = Sparepart::tersedia()
                ->orderBy('nama_suku_cadang', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar suku cadang tersedia berhasil dimuat',
                'data'    => $daftarSukuCadang,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar suku cadang tersedia',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}