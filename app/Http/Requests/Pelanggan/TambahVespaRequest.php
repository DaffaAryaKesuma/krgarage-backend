<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi data saat pelanggan menambah Vespa baru.
class TambahVespaRequest extends FormRequest {
    // true karena hak akses pelanggan sudah dijaga oleh middleware route.
    public function authorize(): bool { return true; }

    // Normalisasi plat nomor sebelum validasi unique dijalankan.
    protected function prepareForValidation(): void {
        if ($this->has('plat_nomor')) {
            $this->merge([
                'plat_nomor' => strtoupper(trim((string) $this->input('plat_nomor'))),
            ]);
        }
    }

    // rules() memastikan data Vespa baru lengkap sebelum disimpan.
    public function rules(): array {
        return [
            // Model Vespa wajib diisi, misalnya PX 150 atau Excel.
            'model'          => 'required|string|max:255',
            // Tahun produksi wajib berupa angka.
            'tahun_produksi' => 'required|integer',
            // Plat nomor wajib unik agar satu plat tidak terdaftar ganda.
            'plat_nomor'     => 'required|string|max:20|unique:vespa',
        ];
    }
}
