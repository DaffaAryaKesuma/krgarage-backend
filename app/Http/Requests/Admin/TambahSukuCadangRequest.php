<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class TambahSukuCadangRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'id_suku_cadang' => 'required|exists:suku_cadang,id',
            'jumlah'         => 'required|integer|min:1',
        ];
    }
}
