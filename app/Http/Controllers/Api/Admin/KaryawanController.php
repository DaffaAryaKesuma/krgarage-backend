<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:pengguna',
            'no_telepon' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'mekanik'])],
        ]);

        $karyawan = User::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'no_telepon' => $validated['no_telepon'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Karyawan berhasil ditambahkan.',
            'data' => $karyawan
        ], 201);
    }

    /**
     * Mengupdate data karyawan.
     */
    public function update(Request $request, $id)
    {
        $karyawan = User::whereIn('role', ['admin', 'mekanik'])->findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('pengguna')->ignore($karyawan->id)],
            'no_telepon' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in(['admin', 'mekanik'])],
        ]);

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
