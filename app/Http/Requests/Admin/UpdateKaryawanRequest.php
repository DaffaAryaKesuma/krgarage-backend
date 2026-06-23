<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('no_telepon')) {
            return;
        }

        $nomorTelepon = preg_replace('/\s+/', '', trim((string) $this->input('no_telepon')));

        if (str_starts_with($nomorTelepon, '+62')) {
            $nomorTelepon = '0' . substr($nomorTelepon, 3);
        }

        $this->merge(['no_telepon' => $nomorTelepon]);
    }

    public function rules(): array
    {
        $tabelPengguna = (new User())->getTable();
        $idKaryawan = $this->route('id');

        return [
            'nama' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[\pL\s]+$/u'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($tabelPengguna, 'email')->ignore($idKaryawan),
            ],
            'no_telepon' => [
                'required',
                'string',
                'regex:/^08[0-9]{8,13}$/',
                Rule::unique($tabelPengguna, 'no_telepon')->ignore($idKaryawan),
            ],
            'password' => ['nullable', 'string', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', Rule::in(['admin', 'mekanik'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min' => 'Nama minimal 3 karakter.',
            'nama.regex' => 'Nama hanya boleh mengandung huruf dan spasi.',
            'no_telepon.regex' => 'Format nomor telepon tidak valid.',
            'no_telepon.unique' => 'Nomor telepon sudah digunakan.',
            'password.mixed' => 'Password harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Password harus mengandung minimal satu angka.',
        ];
    }
}
