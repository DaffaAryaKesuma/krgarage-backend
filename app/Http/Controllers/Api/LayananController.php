<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Layanan;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class LayananController extends Controller
{
    /**
     * Menampilkan semua data layanan.
     */
    public function index()
    {
        try {
            $daftarLayanan = Layanan::select('id', 'nama_layanan', 'deskripsi', 'harga', 'durasi_pengerjaan', 'gambar')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($daftarLayanan);

        } catch (QueryException $e) {
            \Log::error('Database error di LayananController@index: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Database tidak tersedia. Pastikan MySQL server berjalan.',
                'layanan' => [],
            ], 500);
        }
    }

    /**
     * Menampilkan satu data layanan berdasarkan ID.
     */
    public function show(Layanan $layanan)
    {
        return response()->json($layanan);
    }
}
