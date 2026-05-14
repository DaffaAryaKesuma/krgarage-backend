<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusPemesananRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'status' => 'required|string|in:Menunggu,Dikonfirmasi,Dikerjakan,Selesai,batal',
            'catatan_mekanik' => 'required_if:status,Selesai|string|max:1000',
        ];
    }
}
