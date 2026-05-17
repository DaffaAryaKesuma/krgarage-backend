<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuatPemesananRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'id_vespa' => [
                'required',
                Rule::exists('vespa', 'id')->where(function ($query) {
                    return $query->where('id_pengguna', $this->user()->id);
                }),
            ],
            'id_layanan'        => 'required|array',
            'id_layanan.*'      => 'exists:layanan,id',
            'tanggal_pemesanan' => 'required|date|after_or_equal:today',
            'jam_pemesanan'     => 'required|string',
            'catatan_pelanggan' => 'nullable|string',
        ];
    }
}

