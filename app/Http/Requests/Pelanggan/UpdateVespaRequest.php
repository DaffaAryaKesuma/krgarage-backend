<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Request ini memvalidasi data saat pelanggan mengubah data Vespa.
class UpdateVespaRequest extends FormRequest {
    // true karena kepemilikan Vespa dan role dicek di route/controller.
    public function authorize(): bool { return true; }

    // rules() mirip tambah Vespa, tetapi plat nomor boleh sama dengan data Vespa saat ini.
    public function rules(): array {
        return [
            'model'          => 'required|string|max:255',
            'tahun_produksi' => 'required|integer',
            // ignore() mencegah validasi unique menolak plat milik record yang sedang diedit.
            'plat_nomor'     => ['required', 'string', 'max:20', Rule::unique('vespa')->ignore($this->route('vespa'))],
        ];
    }
}
