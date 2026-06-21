<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Request ini memvalidasi data saat pelanggan mengubah data Vespa.
class UpdateVespaRequest extends FormRequest {
    // true karena kepemilikan Vespa dan role dicek di route/controller.
    public function authorize(): bool { return true; }

    // Normalisasi plat nomor sebelum validasi unique dijalankan.
    protected function prepareForValidation(): void {
        if ($this->has('plat_nomor')) {
            $platNomor = preg_replace('/\s+/', ' ', trim((string) $this->input('plat_nomor')));

            $this->merge([
                'plat_nomor' => strtoupper($platNomor),
            ]);
        }
    }

    // rules() mirip tambah Vespa, tetapi plat nomor boleh sama dengan data Vespa saat ini.
    public function rules(): array {
        return [
            'model'          => ['required', 'string', 'min:2', 'max:50'],
            'tahun_produksi' => ['required', 'integer', 'min:1946', 'max:' . (now()->year + 1)],
            // ignore() mencegah validasi unique menolak plat milik record yang sedang diedit.
            'plat_nomor'     => [
                'required',
                'string',
                'min:3',
                'max:15',
                'regex:/^[A-Z0-9 ]+$/',
                Rule::unique('vespa')->ignore($this->route('vespa')),
            ],
        ];
    }

    public function messages(): array {
        return [
            'model.min'              => 'Model Vespa minimal 2 karakter.',
            'model.max'              => 'Model Vespa maksimal 50 karakter.',
            'tahun_produksi.min'     => 'Tahun produksi Vespa minimal 1946.',
            'tahun_produksi.max'     => 'Tahun produksi Vespa maksimal ' . (now()->year + 1) . '.',
            'plat_nomor.min'         => 'Plat nomor minimal 3 karakter.',
            'plat_nomor.max'         => 'Plat nomor maksimal 15 karakter.',
            'plat_nomor.regex'       => 'Plat nomor hanya boleh mengandung huruf, angka, dan spasi.',
            'plat_nomor.unique'      => 'Plat nomor sudah terdaftar.',
        ];
    }
}
