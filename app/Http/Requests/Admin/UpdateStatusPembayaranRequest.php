<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusPembayaranRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'status_pembayaran' => 'required|string|in:Belum Lunas,Lunas',
        ];
    }
}
