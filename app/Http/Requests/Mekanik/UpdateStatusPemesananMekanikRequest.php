<?php
namespace App\Http\Requests\Mekanik;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusPemesananMekanikRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'status' => 'required|in:Selesai',
            'catatan_mekanik' => 'required|string|max:1000',
        ];
    }
}
