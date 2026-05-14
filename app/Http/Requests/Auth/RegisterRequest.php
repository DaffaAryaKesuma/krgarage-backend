<?php
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest {
    public function rules(): array {
        $tabelPengguna = (new User())->getTable();
        return [
            'nama'       => 'required|string|max:255',
            'email'      => ['nullable', 'string', 'email', 'max:255', Rule::unique($tabelPengguna, 'email')],
            'no_telepon' => 'required|string|max:20',
            'password'   => 'required|string|min:8',
        ];
    }
}
