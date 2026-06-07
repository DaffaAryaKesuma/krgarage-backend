<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi perubahan status pemesanan oleh admin.
class UpdateStatusPemesananRequest extends FormRequest {
    // true karena akses admin sudah dijaga oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() memastikan status yang dikirim termasuk status yang dikenal sistem.
    public function rules(): array {
        return [
            // Status hanya boleh salah satu dari alur servis yang tersedia.
            'status' => 'required|string|in:Menunggu,Dikonfirmasi,Dikerjakan,Selesai,Batal,batal',
            // Jika admin menandai selesai, catatan mekanik wajib ikut dikirim.
            'catatan_mekanik' => 'required_if:status,Selesai|string|max:1000',
        ];
    }
}
