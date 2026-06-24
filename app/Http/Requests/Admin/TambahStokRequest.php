<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi proses restock suku cadang.
class TambahStokRequest extends FormRequest
{
    /**
     * Izinkan request lanjut; role admin sudah dijaga oleh middleware route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk penambahan stok.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Jumlah restock minimal 1 karena stok harus bertambah.
            'jumlah' => 'required|integer|min:1',
            // Harga beli satuan dipakai menghitung total pengeluaran restock.
            'harga_beli_satuan' => 'required|integer|min:0',
            // Jika true, master harga_beli ikut diperbarui memakai harga restock terbaru.
            'update_harga_beli' => 'sometimes|boolean',
            // Catatan opsional untuk keterangan restock.
            'catatan' => 'nullable|string|max:1000',
            // Foto struk wajib sebagai bukti pengeluaran restock.
            // HEIC/HEIF didukung agar foto langsung dari ponsel tetap dapat diunggah.
            'foto_struk' => 'required|file|mimes:jpeg,jpg,png,webp,heic,heif|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'foto_struk.required' => 'Foto struk pembelian wajib diunggah.',
            'foto_struk.file' => 'Foto struk pembelian harus berupa file.',
            'foto_struk.mimes' => 'Format foto struk harus JPG, JPEG, PNG, WebP, HEIC, atau HEIF.',
            'foto_struk.max' => 'Ukuran foto struk maksimal 10MB.',
        ];
    }
}
