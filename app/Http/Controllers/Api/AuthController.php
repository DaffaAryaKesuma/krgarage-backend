<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Registrasi pengguna baru.
     */
    public function daftar(Request $request)
    {
        $tabelPengguna = (new User())->getTable();

        $dataTervalidasi = $request->validate([
            'nama'       => 'required|string|max:255',
            'email'      => ['nullable', 'string', 'email', 'max:255', Rule::unique($tabelPengguna, 'email')],
            'no_telepon' => 'required|string|max:20',
            'password'   => 'required|string|min:8',
        ]);

        // Hapus karakter selain angka dari nomor telepon (spasi, strip, dll)
        $nomorBersih = preg_replace('/[^0-9]/', '', $dataTervalidasi['no_telepon']);

        // Cek apakah nomor telepon sudah terdaftar
        $penggunaSudahAda = User::where('no_telepon', $nomorBersih)->first();

        if ($penggunaSudahAda) {
            return response()->json([
                'message' => 'Nomor telepon sudah terdaftar. Silakan login.',
            ], 422);
        }

        // Buat akun baru
        $pengguna = User::create([
            'nama'       => $dataTervalidasi['nama'],
            'email'      => $dataTervalidasi['email'] ?? $nomorBersih . '@krgarage.com',
            'no_telepon' => $nomorBersih,
            'password'   => Hash::make($dataTervalidasi['password']),
            'role'       => 'pelanggan',
        ]);

        $token = $pengguna->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registrasi berhasil!',
            'access_token' => $token,
            'user'         => $pengguna,
        ]);
    }

    /**
     * Login pengguna.
     */
    public function masuk(Request $request)
    {
        $kredensial = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Periksa kredensial login
        if (!Auth::attempt($kredensial)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $pengguna = Auth::user();
        $token    = $pengguna->createToken('auth_token')->plainTextToken;

        $roleTernormalisasi = RoleNormalizer::normalizeOrNull($pengguna->role);
        if ($roleTernormalisasi && $roleTernormalisasi !== $pengguna->role) {
            $pengguna->forceFill(['role' => $roleTernormalisasi])->save();
        }

        $roleUntukResponse = $roleTernormalisasi ?? ($pengguna->role ?: RoleNormalizer::normalize(null));

        return response()->json([
            'access_token' => $token,
            'token'        => $token,
            'user'         => [
                'id'         => $pengguna->id,
                'nama'       => $pengguna->nama,
                'email'      => $pengguna->email,
                'role'       => $roleUntukResponse,
                'no_telepon' => $pengguna->no_telepon,
            ],
        ]);
    }

    /**
     * Logout pengguna yang sedang login.
     */
    public function keluar(Request $request)
    {
        $pengguna = $request->user();

        if ($pengguna && $pengguna->currentAccessToken()) {
            $pengguna->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}