<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ServiceController extends Controller
{
    /**
     * Menampilkan semua data layanan.
     */
    public function index()
    {
        try {
            $daftarLayanan = Service::select('id', 'nama_layanan', 'deskripsi', 'harga', 'durasi_pengerjaan', 'gambar')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json($daftarLayanan);

        } catch (QueryException $e) {
            \Log::error('Database error di ServiceController@index: ' . $e->getMessage());
            return response()->json([
                'error'   => 'Database tidak tersedia. Pastikan MySQL server berjalan.',
                'layanan' => [],
            ], 500);
        }
    }

    /**
     * Menampilkan satu data layanan berdasarkan ID.
     */
    public function show(Service $layanan)
    {
        return response()->json($layanan);
    }
}