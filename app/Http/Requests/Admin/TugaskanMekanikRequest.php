<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi proses admin menugaskan mekanik ke pemesanan.
class TugaskanMekanikRequest extends FormRequest {
    // true karena role admin sudah dibatasi di route.
    public function authorize(): bool { return true; }

    // rules() memastikan id mekanik yang dipilih ada di tabel pengguna.
    public function rules(): array {
        return [
            // nullable berarti admin juga bisa mengosongkan mekanik jika fitur controller mengizinkan.
            'id_mekanik' => 'nullable|exists:pengguna,id',
        ];
    }
}
