<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Resource ini mengatur bentuk JSON untuk data suku cadang.
class SukuCadangResource extends JsonResource
{
    /**
     * Ubah model suku cadang menjadi array yang siap dikirim sebagai JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Data utama suku cadang.
            'id' => $this->id,
            'nama_suku_cadang' => $this->nama_suku_cadang,
            'id_kategori' => $this->id_kategori,
            // kategori berisi nama singkat agar frontend mudah menampilkan teks kategori.
            'kategori' => $this->kategori ? $this->kategori->nama : null,
            // kategori_detail hanya dikirim jika relasi kategori memang sudah diload.
            'kategori_detail' => $this->whenLoaded('kategori'),
            // Data stok dan harga untuk inventaris dan laporan.
            'jumlah_stok' => $this->jumlah_stok,
            'harga_beli' => $this->harga_beli,
            'harga_jual' => $this->harga_jual,
            'batas_minimal_stok' => $this->batas_minimal_stok,
            // stok_menipis menjadi boolean praktis untuk badge/peringatan di frontend.
            'stok_menipis' => (int) $this->jumlah_stok <= (int) $this->batas_minimal_stok,
            'deskripsi' => $this->deskripsi,
            // Timestamp dikirim agar admin bisa melihat umur/perubahan data.
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
