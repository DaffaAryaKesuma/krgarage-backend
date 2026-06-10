<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriSukuCadang;
use App\Services\LogAktivitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminKategoriSukuCadangController extends Controller
{
    protected $logAktivitasAdmin;

    public function __construct(LogAktivitasService $logAktivitasAdmin)
    {
        $this->logAktivitasAdmin = $logAktivitasAdmin;
    }

    public function index()
    {
        $categories = KategoriSukuCadang::query()
            ->orderBy('nama')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori suku cadang berhasil dimuat',
            'data' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255|unique:kategori_suku_cadang,nama',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = KategoriSukuCadang::create([
            'nama' => trim($request->nama),
        ]);
        $this->logAktivitasAdmin->catat(
            $request->user(),
            'tambah',
            'inventaris',
            'kategori_suku_cadang',
            $category->id,
            $category->nama,
            "Menambahkan kategori suku cadang {$category->nama}",
            null,
            $category->only(['nama']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category,
        ], 201);
    }
}
