<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPemesanan extends Model
{
    use HasFactory;

    protected $table = 'item_pemesanan';

    protected $fillable = [
        'id_pemesanan',
        'id_suku_cadang',
        'nama_suku_cadang_saat_ini',
        'jumlah',
        'harga_saat_ini',
    ];

    /**
     * Relasi: Item pemesanan milik sebuah pemesanan
     */
    public function pemesanan()
    {
        return $this->belongsTo(Pemesanan::class, 'id_pemesanan');
    }

    /**
     * Relasi: Item pemesanan terkait dengan satu suku cadang
     */
    public function sukuCadang()
    {
        // Riwayat transaksi tetap dapat membaca master suku cadang yang sudah dihapus dari daftar aktif.
        return $this->belongsTo(SukuCadang::class, 'id_suku_cadang')->withTrashed();
    }
}
