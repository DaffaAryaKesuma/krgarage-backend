<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi data saat admin membuat master suku cadang baru.
class SimpanSukuCadangRequest extends FormRequest
{
    /**
     * Izinkan request lanjut; role admin sudah dijaga oleh middleware route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk data suku cadang baru.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Nama dan kategori wajib agar barang mudah dicari dan dikelompokkan.
            'nama_suku_cadang'   => 'required|string|max:255',
            'id_kategori'        => 'required|exists:kategori_suku_cadang,id',
            // Stok dan harga tidak boleh bernilai negatif.
            'jumlah_stok'        => 'required|integer|min:0',
            'harga_beli'         => 'required|numeric|min:0',
            'harga_jual'         => 'required|numeric|min:0',
            // Batas minimal dipakai untuk peringatan stok menipis.
            'batas_minimal_stok' => 'required|integer|min:0',
            // Deskripsi opsional untuk detail tambahan.
            'deskripsi'          => 'nullable|string',
        ];
    }
}
