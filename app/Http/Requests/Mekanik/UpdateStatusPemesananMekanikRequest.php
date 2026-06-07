<?php
namespace App\Http\Requests\Mekanik;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi mekanik saat menyelesaikan pekerjaan servis.
class UpdateStatusPemesananMekanikRequest extends FormRequest {
    // true karena akses mekanik sudah dijaga oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() membatasi mekanik hanya bisa mengirim status selesai.
    public function rules(): array {
        return [
            // Mekanik tidak boleh mengubah status ke Menunggu/Dikonfirmasi/Batal.
            'status' => 'required|in:Selesai',
            // Catatan mekanik wajib agar ada bukti ringkas pekerjaan yang dilakukan.
            'catatan_mekanik' => 'required|string|max:1000',
        ];
    }
}
