<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

// Request ini memvalidasi data pendaftaran pelanggan baru.
class RegisterRequest extends FormRequest {
    // Samakan format nomor telepon sebelum validasi format dan keunikan dijalankan.
    protected function prepareForValidation(): void {
        if (!$this->has('no_telepon')) {
            return;
        }

        $nomorTelepon = preg_replace('/\s+/', '', trim((string) $this->input('no_telepon')));

        // Simpan nomor Indonesia dalam format 08 agar +628 dan 08 tidak dianggap berbeda.
        if (str_starts_with($nomorTelepon, '+62')) {
            $nomorTelepon = '0' . substr($nomorTelepon, 3);
        }

        $this->merge([
            'no_telepon' => $nomorTelepon,
        ]);
    }

    // rules() menentukan input apa saja yang boleh masuk ke proses register.
    public function rules(): array {
        // Ambil nama tabel dari model agar validasi unique tetap aman jika nama tabel berubah.
        $tabelPengguna = (new User())->getTable();
        return [
            // Aturan ini disamakan dengan validasi form registrasi frontend.
            'nama'       => ['required', 'string', 'min:3', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', Rule::unique($tabelPengguna, 'email')],
            'no_telepon' => [
                'required',
                'string',
                'regex:/^08[0-9]{8,13}$/',
                Rule::unique($tabelPengguna, 'no_telepon'),
            ],
            'password'   => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array {
        return [
            'nama.min'             => 'Nama minimal 3 karakter.',
            'email.required'       => 'Email wajib diisi.',
            'no_telepon.regex'     => 'Format nomor HP tidak valid.',
            'no_telepon.unique'    => 'Nomor telepon sudah terdaftar. Silakan login.',
            'password.mixed'       => 'Password harus mengandung huruf besar dan huruf kecil.',
            'password.numbers'     => 'Password harus mengandung minimal satu angka.',
        ];
    }
}
