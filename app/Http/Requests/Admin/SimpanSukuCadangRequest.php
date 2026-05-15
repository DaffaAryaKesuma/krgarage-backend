<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SimpanSukuCadangRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_suku_cadang'   => 'required|string|max:255',
            'id_kategori'        => 'required|exists:kategori_suku_cadang,id',
            'jumlah_stok'        => 'required|integer|min:0',
            'harga_beli'         => 'required|numeric|min:0',
            'harga_jual'         => 'required|numeric|min:0',
            'batas_minimal_stok' => 'required|integer|min:0',
            'deskripsi'          => 'nullable|string',
        ];
    }
}
