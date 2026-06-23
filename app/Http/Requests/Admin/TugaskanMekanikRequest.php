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
            // Pemesanan boleh belum memiliki mekanik sebelum ditugaskan, tetapi aksi penugasan wajib memilih mekanik.
            'id_mekanik' => 'required|exists:pengguna,id',
        ];
    }
}
