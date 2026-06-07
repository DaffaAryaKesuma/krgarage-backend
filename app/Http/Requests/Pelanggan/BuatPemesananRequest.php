<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Request ini memvalidasi data saat pelanggan membuat pemesanan servis.
class BuatPemesananRequest extends FormRequest {
    // true berarti request boleh lanjut; pembatasan role dilakukan oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() menjadi pagar agar data pemesanan yang masuk lengkap dan valid.
    public function rules(): array {
        return [
            'id_vespa' => [
                'required',
                // Vespa harus benar-benar milik user yang sedang login.
                Rule::exists('vespa', 'id')->where(function ($query) {
                    return $query->where('id_pengguna', $this->user()->id);
                }),
            ],
            // Pelanggan boleh memilih lebih dari satu layanan.
            'id_layanan'        => 'required|array',
            // Setiap id layanan di array harus ada di tabel layanan.
            'id_layanan.*'      => 'exists:layanan,id',
            // Tanggal pemesanan tidak boleh di masa lalu.
            'tanggal_pemesanan' => 'required|date|after_or_equal:today',
            // Jam dikirim sebagai string karena format jam diproses di controller/frontend.
            'jam_pemesanan'     => 'required|string',
            // Catatan pelanggan opsional.
            'catatan_pelanggan' => 'nullable|string',
        ];
    }
}
