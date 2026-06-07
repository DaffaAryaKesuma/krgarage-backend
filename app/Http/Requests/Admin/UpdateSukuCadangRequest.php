<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// Request ini memvalidasi perubahan data master suku cadang.
class UpdateSukuCadangRequest extends FormRequest
{
    /**
     * Izinkan request lanjut; role admin sudah dijaga oleh middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk update parsial data suku cadang.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // sometimes berarti field boleh tidak dikirim, tetapi jika dikirim harus valid.
            'nama_suku_cadang'   => 'sometimes|string|max:255',
            'id_kategori'        => 'sometimes|exists:kategori_suku_cadang,id',
            'jumlah_stok'        => 'sometimes|integer|min:0',
            'harga_beli'         => 'sometimes|numeric|min:0',
            'harga_jual'         => 'sometimes|numeric|min:0',
            'batas_minimal_stok' => 'sometimes|integer|min:0',
            // Deskripsi boleh kosong/null.
            'deskripsi'          => 'nullable|string',
        ];
    }
}
