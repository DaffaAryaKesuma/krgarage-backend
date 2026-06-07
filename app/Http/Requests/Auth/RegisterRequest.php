<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

// Request ini memvalidasi data pendaftaran pelanggan baru.
class RegisterRequest extends FormRequest {
    // rules() menentukan input apa saja yang boleh masuk ke proses register.
    public function rules(): array {
        // Ambil nama tabel dari model agar validasi unique tetap aman jika nama tabel berubah.
        $tabelPengguna = (new User())->getTable();
        return [
            // Nama wajib diisi dan maksimal 255 karakter.
            'nama'       => 'required|string|max:255',
            // Email boleh kosong, tetapi jika diisi harus valid dan unik di tabel pengguna.
            'email'      => ['nullable', 'string', 'email', 'max:255', Rule::unique($tabelPengguna, 'email')],
            // Nomor telepon wajib karena bisa dipakai untuk kontak pelanggan.
            'no_telepon' => 'required|string|max:20',
            // Password minimal 8 karakter sebelum disimpan dalam bentuk hash.
            'password'   => 'required|string|min:8',
        ];
    }
}
