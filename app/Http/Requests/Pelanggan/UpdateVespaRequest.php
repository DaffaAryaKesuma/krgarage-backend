<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVespaRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'model'          => 'required|string|max:255',
            'tahun_produksi' => 'required|integer',
            'plat_nomor'     => ['required', 'string', 'max:20', Rule::unique('vespa')->ignore($this->route('vespa'))],
        ];
    }
}
