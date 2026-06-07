<?php
namespace App\Http\Requests\Mekanik;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi suku cadang yang dipakai mekanik pada pemesanan.
class TambahSukuCadangRequest extends FormRequest {
    // true karena akses mekanik sudah dijaga oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() memastikan suku cadang dan jumlahnya valid.
    public function rules(): array {
        return [
            // ID suku cadang harus tersedia di tabel master.
            'id_suku_cadang' => 'required|exists:suku_cadang,id',
            // Jumlah minimal 1 agar tidak ada penggunaan barang kosong.
            'jumlah'         => 'required|integer|min:1',
        ];
    }
}
