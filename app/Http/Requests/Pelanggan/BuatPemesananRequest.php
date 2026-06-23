<?php
namespace App\Http\Requests\Pelanggan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

// Request ini memvalidasi data saat pelanggan membuat pemesanan servis.
class BuatPemesananRequest extends FormRequest {
    private const JAM_OPERASIONAL = ['10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

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
            'id_layanan'        => ['required', 'array', 'min:1'],
            // Setiap id layanan di array harus ada di tabel layanan.
            'id_layanan.*'      => ['integer', 'distinct', 'exists:layanan,id'],
            // Tanggal pemesanan tidak boleh di masa lalu.
            'tanggal_pemesanan' => 'required|date|after_or_equal:today',
            // Jam dikirim sebagai string karena format jam diproses di controller/frontend.
            'jam_pemesanan'     => ['required', 'string', Rule::in(self::JAM_OPERASIONAL)],
            // Catatan pelanggan opsional.
            'catatan_pelanggan' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'id_vespa.required' => 'Vespa wajib dipilih.',
            'id_vespa.exists' => 'Vespa yang dipilih tidak valid atau bukan milik Anda.',
            'id_layanan.required' => 'Layanan wajib dipilih.',
            'id_layanan.array' => 'Format layanan yang dipilih tidak valid.',
            'id_layanan.min' => 'Pilih minimal satu layanan.',
            'id_layanan.*.integer' => 'ID layanan harus berupa angka.',
            'id_layanan.*.distinct' => 'Layanan yang sama tidak boleh dipilih lebih dari satu kali.',
            'id_layanan.*.exists' => 'Layanan yang dipilih tidak tersedia.',
            'tanggal_pemesanan.required' => 'Tanggal pemesanan wajib diisi.',
            'tanggal_pemesanan.date' => 'Format tanggal pemesanan tidak valid.',
            'tanggal_pemesanan.after_or_equal' => 'Tanggal pemesanan tidak boleh berada di masa lalu.',
            'jam_pemesanan.required' => 'Jam pemesanan wajib dipilih.',
            'jam_pemesanan.string' => 'Format jam pemesanan tidak valid.',
            'jam_pemesanan.in' => 'Jam pemesanan tidak termasuk jam operasional bengkel.',
            'catatan_pelanggan.string' => 'Catatan pelanggan harus berupa teks.',
            'catatan_pelanggan.max' => 'Catatan pelanggan maksimal 1000 karakter.',
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
                $tanggalPemesanan = Carbon::parse($tanggal, config('app.timezone'));
                if ($tanggalPemesanan->isFriday()) {
                    $validator->errors()->add('tanggal_pemesanan', 'Bengkel libur setiap hari Jumat. Silakan pilih tanggal lain.');
                    return;
                }

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
