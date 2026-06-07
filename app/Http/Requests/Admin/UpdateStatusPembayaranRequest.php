<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi perubahan status pembayaran oleh admin.
class UpdateStatusPembayaranRequest extends FormRequest {
    // true karena akses admin sudah dicek oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() membatasi status pembayaran agar tidak ada nilai di luar sistem.
    public function rules(): array {
        return [
            // Pembayaran hanya punya dua kondisi: belum lunas atau lunas.
            'status_pembayaran' => 'required|string|in:Belum Lunas,Lunas',
        ];
    }
}
