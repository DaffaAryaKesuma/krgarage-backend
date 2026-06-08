<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

// Request ini memvalidasi data saat pelanggan membuat pemesanan servis.
class BuatPemesananRequest extends FormRequest {
    // true berarti request boleh lanjut; pembatasan role dilakukan oleh middleware route.
    public function authorize(): bool { return true; }

    // rules() menjadi pagar agar data pemesanan yang masuk lengkap dan valid.
    public function rules(): array {
        return [
            'id_vespa' => [
                'required',
                // Vespa harus benar-benar milik user yang sedang login.
                Rule::exists('vespa', 'id')->where(function ($query) {
                    return $query->where('id_pengguna', $this->user()->id);
                }),
            ],
            // Pelanggan boleh memilih lebih dari satu layanan.
            'id_layanan'        => 'required|array',
            // Setiap id layanan di array harus ada di tabel layanan.
            'id_layanan.*'      => 'exists:layanan,id',
            // Tanggal pemesanan tidak boleh di masa lalu.
            'tanggal_pemesanan' => 'required|date|after_or_equal:today',
            // Jam dikirim sebagai string karena format jam diproses di controller/frontend.
            'jam_pemesanan'     => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            // Catatan pelanggan opsional.
            'catatan_pelanggan' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tanggal = $this->input('tanggal_pemesanan');
            $jam = $this->input('jam_pemesanan');

            if (!$tanggal || !$jam || $validator->errors()->has('tanggal_pemesanan') || $validator->errors()->has('jam_pemesanan')) {
                return;
            }

            try {
                $waktuPemesanan = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $tanggal . ' ' . substr((string) $jam, 0, 5),
                    config('app.timezone')
                );
            } catch (\Throwable) {
                $validator->errors()->add('jam_pemesanan', 'Format jam pemesanan tidak valid.');
                return;
            }

            if ($waktuPemesanan->lessThanOrEqualTo(now())) {
                $validator->errors()->add('jam_pemesanan', 'Jam pemesanan sudah lewat. Silakan pilih jam lain.');
            }
        });
    }
}
