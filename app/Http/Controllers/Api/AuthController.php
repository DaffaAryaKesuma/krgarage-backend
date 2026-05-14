<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleNormalizer;
use App\Traits\ApiResponseTrait;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Registrasi pengguna baru.
     */
    public function daftar(RegisterRequest $request)
    {
        try {
            $data = $request->validated();
            $nomorBersih = preg_replace('/[^0-9]/', '', $data['no_telepon']);

            // Cek apakah nomor telepon sudah terdaftar
            $penggunaSudahAda = User::where('no_telepon', $nomorBersih)->first();
            if ($penggunaSudahAda) {
                return $this->errorResponse('Nomor telepon sudah terdaftar. Silakan login.', 422);
            }

            // Buat akun baru
            $pengguna = User::create([
                'nama'       => $data['nama'],
                'email'      => $data['email'] ?? $nomorBersih . '@krgarage.com',
                'no_telepon' => $nomorBersih,
                'password'   => Hash::make($data['password']),
                'role'       => 'pelanggan',
            ]);

            $token = $pengguna->createToken('auth_token')->plainTextToken;

            return $this->successResponse('Registrasi berhasil!', new UserResource($pengguna), 201, [
                'access_token' => $token
            ]);

        } catch (\Exception $e) {
            Log::error('AuthController@daftar: ' . $e->getMessage());
            return $this->errorResponse('Gagal melakukan pendaftaran.', 500, $e);
        }
    }

    /**
     * Login pengguna.
     */
    public function masuk(LoginRequest $request)
    {
        try {
            $kredensial = $request->validated();

            if (!Auth::attempt($kredensial)) {
                return $this->errorResponse('Email atau password salah', 401);
            }

            $pengguna = Auth::user();
            $token    = $pengguna->createToken('auth_token')->plainTextToken;

            $roleTernormalisasi = RoleNormalizer::normalizeOrNull($pengguna->role);
            if ($roleTernormalisasi && $roleTernormalisasi !== $pengguna->role) {
                $pengguna->forceFill(['role' => $roleTernormalisasi])->save();
            }

            return $this->successResponse('Login berhasil!', new UserResource($pengguna), 200, [
                'access_token' => $token,
                'token'        => $token,
            ]);

        } catch (\Exception $e) {
            Log::error('AuthController@masuk: ' . $e->getMessage());
            return $this->errorResponse('Gagal melakukan login.', 500, $e);
        }
    }

    /**
     * Logout pengguna yang sedang login.
     */
    public function keluar(Request $request)
    {
        try {
            $pengguna = $request->user();

            if ($pengguna && $pengguna->currentAccessToken()) {
                $pengguna->currentAccessToken()->delete();
            }

            return $this->successResponse('Logout berhasil.');
        } catch (\Exception $e) {
            Log::error('AuthController@keluar: ' . $e->getMessage());
            return $this->errorResponse('Gagal melakukan logout.', 500, $e);
        }
    }
}
