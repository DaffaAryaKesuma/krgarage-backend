<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $pengguna = $this->user();
        $tabelPengguna = (new User())->getTable();

        return [
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique($tabelPengguna, 'email')->ignore($pengguna?->id),
            ],
            'no_telepon' => [
                'required',
                'string',
                'regex:/^08[0-9]{8,13}$/',
                Rule::unique($tabelPengguna, 'no_telepon')->ignore($pengguna?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min' => 'Nama minimal 3 karakter.',
            'no_telepon.regex' => 'Format nomor HP tidak valid.',
            'no_telepon.unique' => 'Nomor telepon sudah digunakan.',
        ];
    }
}
