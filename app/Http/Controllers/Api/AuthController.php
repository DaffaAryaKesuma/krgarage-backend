<?php

namespace App\Http\Controllers\Api;

// Controller dasar Laravel.
use App\Http\Controllers\Controller;
// Model pengguna dari tabel pengguna.
use App\Models\User;
// Helper untuk menyamakan role lama/baru.
use App\Support\RoleNormalizer;
// Trait response JSON konsisten.
use App\Traits\ApiResponseTrait;
// Form request validasi register dan login.
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
// Resource untuk membentuk response data user.
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
// Hash dipakai untuk membuat dan mengecek password.
use Illuminate\Support\Facades\Hash;
// Auth dipakai untuk proses login.
use Illuminate\Support\Facades\Auth;
// Log dipakai mencatat error server.
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    // Memakai helper successResponse dan errorResponse.
    use ApiResponseTrait;

    /**
     * Registrasi pengguna baru.
     */
    public function daftar(RegisterRequest $request)
    {
        try {
            // Ambil data yang sudah lolos validasi RegisterRequest.
            $data = $request->validated();
            // RegisterRequest sudah menormalkan nomor +628 menjadi format 08.
            $nomorBersih = $data['no_telepon'];

            // Cek apakah nomor telepon sudah terdaftar.
            $penggunaSudahAda = User::where('no_telepon', $nomorBersih)->first();
            if ($penggunaSudahAda) {
                return $this->errorResponse('Nomor telepon sudah terdaftar. Silakan login.', 422);
            }

            // Buat akun baru dengan role default pelanggan.
            $dataPengguna = [
                'nama'       => $data['nama'],
                'email'      => $data['email'],
                'no_telepon' => $nomorBersih,
                'password'   => Hash::make($data['password']),
                'role'       => 'pelanggan',
            ];

            // Kompatibilitas untuk database pengujian SQLite yang masih menyimpan kolom legacy.
            if (Schema::hasColumn((new User())->getTable(), 'name')) {
                $dataPengguna['name'] = $data['nama'];
            }

            $pengguna = User::forceCreate($dataPengguna);

            // Token Sanctum dipakai frontend untuk request berikutnya.
            $token = $pengguna->createToken('auth_token')->plainTextToken;

            // Response mengirim data user dan access_token.
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
            // Ambil email/password yang sudah divalidasi.
            $kredensial = $request->validated();

            // Auth::attempt mengecek kombinasi email dan password.
            if (!Auth::attempt($kredensial)) {
                return $this->errorResponse('Email atau password salah', 401);
            }

            // Setelah login berhasil, ambil user aktif.
            $pengguna = Auth::user();
            // Buat token baru untuk sesi login ini.
            $token    = $pengguna->createToken('auth_token')->plainTextToken;

            // Normalisasi role untuk menjaga data lama tetap cocok dengan role Indonesia.
            $roleTernormalisasi = RoleNormalizer::normalizeOrNull($pengguna->role);
            if ($roleTernormalisasi && $roleTernormalisasi !== $pengguna->role) {
                $pengguna->forceFill(['role' => $roleTernormalisasi])->save();
            }

            // Frontend memakai access_token untuk disimpan di localStorage.
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
            // Ambil user dari token yang sedang aktif.
            $pengguna = $request->user();

            if ($pengguna && $pengguna->currentAccessToken()) {
                // Hapus hanya token saat ini, bukan semua token user.
                $pengguna->currentAccessToken()->delete();
            }

            return $this->successResponse('Logout berhasil.');
        } catch (\Exception $e) {
            Log::error('AuthController@keluar: ' . $e->getMessage());
            return $this->errorResponse('Gagal melakukan logout.', 500, $e);
        }
    }

    /**
     * Mengambil identitas pengguna langsung dari token aktif.
     */
    public function profil(Request $request)
    {
        return $this->successResponse(
            'Profil pengguna berhasil dimuat.',
            new UserResource($request->user())
        );
    }

    /**
     * Memperbarui informasi profil pengguna.
     */
    public function perbaruiProfil(Request $request)
    {
        try {
            // User aktif berasal dari token Sanctum.
            $pengguna = $request->user();
            
            // Validasi langsung di controller karena form ini sederhana.
            $request->validate([
                'nama'       => 'required|string|max:255',
                // Email harus unik kecuali milik user sendiri.
                'email'      => 'required|email|unique:pengguna,email,' . $pengguna->id,
                'no_telepon' => 'required|string|max:20',
            ]);

            // Nomor telepon disimpan hanya angka.
            $nomorBersih = preg_replace('/[^0-9]/', '', $request->no_telepon);

            // Update data profil user.
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
            // User aktif yang ingin mengganti password.
            $pengguna = $request->user();

            // Validasi password lama dan password baru.
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

            // Password lama harus cocok sebelum password baru disimpan.
            if (!Hash::check($request->password_lama, $pengguna->password)) {
                return $this->errorResponse('Password lama tidak sesuai.', 422);
            }

            // Password baru disimpan dalam bentuk hash.
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
