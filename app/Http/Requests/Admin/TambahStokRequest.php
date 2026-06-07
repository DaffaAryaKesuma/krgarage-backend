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
            // Foto struk pembelian opsional sebagai bukti restock.
            'foto_struk' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
