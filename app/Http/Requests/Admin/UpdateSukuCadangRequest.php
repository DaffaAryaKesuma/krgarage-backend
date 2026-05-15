<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSukuCadangRequest extends FormRequest
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
            'nama_suku_cadang'   => 'sometimes|string|max:255',
            'id_kategori'        => 'sometimes|exists:kategori_suku_cadang,id',
            'harga_beli'         => 'sometimes|numeric|min:0',
            'harga_jual'         => 'sometimes|numeric|min:0',
            'batas_minimal_stok' => 'sometimes|integer|min:0',
            'deskripsi'          => 'nullable|string',
        ];
    }
}
