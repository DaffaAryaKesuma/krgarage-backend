<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriInventaris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminKategoriInventarisController extends Controller
{
    public function index()
    {
        $categories = KategoriInventaris::query()
            ->orderBy('nama')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori inventori berhasil dimuat',
            'data' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255|unique:kategori_inventori,nama',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = KategoriInventaris::create([
            'nama' => trim($request->nama),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category,
        ], 201);
    }
}

