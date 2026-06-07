<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Resource ini mengatur bentuk JSON pemesanan sebelum dikirim ke frontend.
class PemesananResource extends JsonResource {
    // toArray() dipanggil otomatis Laravel saat resource dijadikan response API.
    public function toArray(Request $request): array {
        // Ambil dulu semua atribut asli dari model Pemesanan.
        $data = parent::toArray($request);
        
        // Pastikan field status_pembayaran selalu ada, walaupun nilainya null.
        $data['status_pembayaran'] = $this->status_pembayaran ?? null;
        // Field status dipastikan ada agar frontend tidak perlu menebak nama kolom.
        $data['status'] = $this->status ?? null;
        // completed_at dikirim agar frontend bisa menampilkan waktu servis selesai.
        $data['completed_at'] = $this->completed_at;
        // paid_at dikirim agar frontend bisa menampilkan waktu pembayaran dilunaskan.
        $data['paid_at'] = $this->paid_at;
        
        // Kembalikan data final ke response JSON.
        return $data;
    }
}
