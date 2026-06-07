<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi suku cadang yang ditambahkan admin ke pemesanan.
class TambahSukuCadangRequest extends FormRequest {
    // true karena akses admin sudah dijaga oleh middleware.
    public function authorize(): bool { return true; }

    // rules() memastikan barang dan jumlahnya valid.
    public function rules(): array {
        return [
            // Suku cadang harus ada di tabel master suku_cadang.
            'id_suku_cadang' => 'required|exists:suku_cadang,id',
            // Jumlah minimal 1 agar tidak ada item kosong.
            'jumlah'         => 'required|integer|min:1',
        ];
    }
}
