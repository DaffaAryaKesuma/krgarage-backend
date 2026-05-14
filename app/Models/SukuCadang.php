<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SukuCadang extends Model
{
    use HasFactory;

    protected $table = 'suku_cadang';

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'nama_suku_cadang',
        'kategori',
        'jumlah_stok',
        'harga_beli',
        'harga_jual',
        'batas_minimal_stok',
        'deskripsi',
    ];

    protected $appends = ['stok_menipis'];

    /**
     * Accessor: Cek apakah stok suku cadang sudah menipis.
     */
    public function getStokMenipisAttribute(): bool
    {
        return $this->jumlah_stok <= $this->batas_minimal_stok;
    }

    /**
     * Relasi: Suku cadang memiliki banyak item pemesanan.
     */
    public function itemPemesanan()
    {
        return $this->hasMany(ItemPemesanan::class, 'id_suku_cadang');
    }

    /**
     * Query scope: Filter suku cadang yang masih tersedia (stok > 0).
     */
    public function scopeTersedia($query)
    {
        return $query->where('jumlah_stok', '>', 0);
    }

    /**
     * Query scope: Filter suku cadang yang stoknya menipis.
     */
    public function scopeStokMenipis($query)
    {
        return $query->whereRaw('jumlah_stok <= batas_minimal_stok');
    }
}

