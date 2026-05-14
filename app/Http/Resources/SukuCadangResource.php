<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SukuCadangResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_suku_cadang' => $this->nama_suku_cadang,
            'kategori' => $this->kategori,
            'jumlah_stok' => $this->jumlah_stok,
            'harga_beli' => $this->harga_beli,
            'harga_jual' => $this->harga_jual,
            'batas_minimal_stok' => $this->batas_minimal_stok,
            'stok_menipis' => (int) $this->jumlah_stok <= (int) $this->batas_minimal_stok,
            'deskripsi' => $this->deskripsi,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
