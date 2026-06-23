<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKaryawanRequest;
use App\Http\Requests\Admin\UpdateKaryawanRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class KaryawanController extends Controller
{
    /**
     * Menampilkan daftar semua karyawan (Admin & Mekanik).
     */
    public function index()
    {
        // Hanya ambil user dengan role admin dan mekanik
        $karyawan = User::whereIn('role', ['admin', 'mekanik'])
                        ->orderBy('created_at', 'desc')
                        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $karyawan
        ]);
    }

    /**
     * Menambahkan karyawan baru.
     */
    public function store(StoreKaryawanRequest $request)
    {
        $validated = $request->validated();

        $dataKaryawan = [
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'no_telepon' => $validated['no_telepon'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ];

        if (Schema::hasColumn((new User())->getTable(), 'name')) {
            $dataKaryawan['name'] = $validated['nama'];
        }

        $karyawan = User::forceCreate($dataKaryawan);

        return response()->json([
            'status' => 'success',
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => $karyawan
        ], 201);
    }

    /**
     * Mengupdate data karyawan.
     */
    public function update(UpdateKaryawanRequest $request, $id)
    {
        $karyawan = User::whereIn('role', ['admin', 'mekanik'])->findOrFail($id);
        $validated = $request->validated();

        $karyawan->nama = $validated['nama'];
        $karyawan->email = $validated['email'];
        $karyawan->no_telepon = $validated['no_telepon'];
        $karyawan->role = $validated['role'];

        if (!empty($validated['password'])) {
            $karyawan->password = Hash::make($validated['password']);
        }

        $karyawan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Data karyawan berhasil diperbarui.',
            'data' => $karyawan
        ]);
    }

    /**
     * Menghapus akun karyawan.
     */
    public function destroy($id)
    {
        $karyawan = User::whereIn('role', ['admin', 'mekanik'])->findOrFail($id);
        
        // Mencegah menghapus diri sendiri
        if (auth()->id() === $karyawan->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak bisa menghapus akun Anda sendiri.'
            ], 403);
        }

        $karyawan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Karyawan berhasil dihapus.'
        ]);
    }
}
