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

    /**
     * Memperbarui informasi profil pengguna.
     */
    public function perbaruiProfil(Request $request)
    {
        try {
            $pengguna = $request->user();
            
            $request->validate([
                'nama'       => 'required|string|max:255',
                'email'      => 'required|email|unique:pengguna,email,' . $pengguna->id,
                'no_telepon' => 'required|string|max:20',
            ]);

            $nomorBersih = preg_replace('/[^0-9]/', '', $request->no_telepon);

            $pengguna->update([
                'nama'       => $request->nama,
                'email'      => $request->email,
                'no_telepon' => $nomorBersih,
            ]);

            return $this->successResponse('Profil berhasil diperbarui!', new UserResource($pengguna));
        } catch (\Exception $e) {
            Log::error('AuthController@perbaruiProfil: ' . $e->getMessage());
            return $this->errorResponse('Gagal memperbarui profil.', 500, $e);
        }
    }

    /**
     * Memperbarui password pengguna.
     */
    public function gantiPassword(Request $request)
    {
        try {
            $pengguna = $request->user();

            $request->validate([
                'password_lama' => 'required|string',
                'password_baru' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/[A-Z]/',      // minimal 1 huruf besar
                    'regex:/[0-9]/',      // minimal 1 angka
                ],
            ], [
                'password_baru.min'      => 'Password baru minimal 8 karakter.',
                'password_baru.regex'    => 'Password baru harus mengandung minimal 1 huruf besar dan 1 angka.',
                'password_baru.confirmed'=> 'Konfirmasi password tidak cocok.',
            ]);

            if (!Hash::check($request->password_lama, $pengguna->password)) {
                return $this->errorResponse('Password lama tidak sesuai.', 422);
            }

            $pengguna->update([
                'password' => Hash::make($request->password_baru),
            ]);

            return $this->successResponse('Password berhasil diganti!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Biarkan Laravel mengembalikan 422, jangan di-wrap jadi 500
            throw $e;
        } catch (\Exception $e) {
            Log::error('AuthController@gantiPassword: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengganti password.', 500, $e);
        }
    }
}
