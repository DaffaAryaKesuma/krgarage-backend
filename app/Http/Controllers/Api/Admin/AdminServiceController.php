<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminServiceController extends Controller
{
    /**
     * Menyimpan data layanan baru.
     */
    public function store(Request $request)
    {
        $dataTervalidasi = $request->validate([
            'nama_layanan'     => 'required|string|max:255',
            'deskripsi'        => 'required|string',
            'harga'            => 'required|integer|min:0',
            'durasi_pengerjaan' => 'nullable|integer|min:5',
            'gambar'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Logika upload gambar
        if ($request->hasFile('gambar')) {
            $pathGambar              = $request->file('gambar')->store('services', 'public');
            $dataTervalidasi['gambar'] = $pathGambar;
        }

        $layanan = Service::create($dataTervalidasi);

        return response()->json($layanan, 201);
    }

    /**
     * Memperbarui data layanan yang sudah ada.
     */
    public function update(Request $request, Service $layanan)
    {
        $dataTervalidasi = $request->validate([
            'nama_layanan'     => 'required|string|max:255',
            'deskripsi'        => 'required|string',
            'harga'            => 'required|integer|min:0',
            'durasi_pengerjaan' => 'nullable|integer|min:5',
            'gambar'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('gambar')) {
            // Hapus gambar lama jika ada
            if ($layanan->gambar) {
                Storage::disk('public')->delete($layanan->gambar);
            }
            // Upload gambar baru
            $pathGambar              = $request->file('gambar')->store('services', 'public');
            $dataTervalidasi['gambar'] = $pathGambar;
        }

        $layanan->update($dataTervalidasi);

        return response()->json($layanan->fresh());
    }

    /**
     * Menghapus data layanan.
     */
    public function destroy(Service $layanan)
    {
        // Hapus file gambar fisik saat data dihapus
        if ($layanan->gambar) {
            Storage::disk('public')->delete($layanan->gambar);
        }

        $layanan->delete();

        return response()->json(['message' => 'Layanan berhasil dihapus']);
    }
}