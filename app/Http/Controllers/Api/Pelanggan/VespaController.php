<?php

namespace App\Http\Controllers\Api\Pelanggan;

use App\Http\Controllers\Controller;
use App\Models\Vespa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VespaController extends Controller
{
    /**
     * Menampilkan daftar vespa milik pengguna yang sedang login.
     */
    public function index(Request $request)
    {
        // Mengambil data vespa dengan informasi servis terakhir menggunakan subquery (tanpa N+1)
        $daftarVespa = $request->user()
            ->vespas()
            ->denganTanggalServisTerakhir()
            ->get()
            ->each(function ($vespa) {
                // Sinkronkan tanggal servis dari hasil subquery (tanpa query tambahan)
                $vespa->sinkronTanggalServisDariAtribut();
                $vespa->save();
            });

        return response()->json($daftarVespa);
    }

    /**
     * Menyimpan data vespa baru.
     */
    public function store(Request $request)
    {
        $dataTervalidasi = $request->validate([
            'model'          => 'required|string|max:255',
            'tahun_produksi' => 'required|integer',
            // Perbaikan: gunakan nama tabel 'vespa' (bukan 'vespas') 
            'plat_nomor'     => 'required|string|max:20|unique:vespa',
        ]);

        // Membuat vespa baru dan langsung menghubungkannya dengan pengguna yang login
        $vespa = $request->user()->vespas()->create($dataTervalidasi);

        return response()->json([
            'message' => 'Vespa berhasil ditambahkan!',
            'data'    => $vespa,
        ], 201);
    }

    /**
     * Memperbarui data vespa.
     */
    public function update(Request $request, Vespa $vespa)
    {
        // Pastikan pengguna hanya bisa mengedit vespa miliknya sendiri
        if ($request->user()->id !== $vespa->id_pengguna) {
            return response()->json(['message' => 'Tidak memiliki akses'], 403);
        }

        $dataTervalidasi = $request->validate([
            'model'          => 'required|string|max:255',
            'tahun_produksi' => 'required|integer',
            // Pastikan plat_nomor unik, menggunakan database 'vespa'
            'plat_nomor'     => ['required', 'string', 'max:20', Rule::unique('vespa')->ignore($vespa->id)],
        ]);

        $vespa->update($dataTervalidasi);

        return response()->json($vespa);
    }

    /**
     * Menghapus data vespa.
     */
    public function destroy(Request $request, Vespa $vespa)
    {
        // Pastikan pengguna hanya bisa menghapus vespa miliknya sendiri
        if ($request->user()->id !== $vespa->id_pengguna) {
            return response()->json(['message' => 'Tidak memiliki akses'], 403);
        }

        $vespa->delete();

        return response()->json(['message' => 'Vespa berhasil dihapus']);
    }

    /**
     * Menampilkan daftar vespa milik pengguna yang perlu diservis.
     */
    public function perluServis(Request $request)
    {
        // Menggunakan subquery untuk menghindari N+1 (satu query)
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

        return response()->json($daftarVespa);
    }
}