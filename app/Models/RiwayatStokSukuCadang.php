<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatStokSukuCadang extends Model
{
    use HasFactory;

    protected $table = 'riwayat_stok_suku_cadang';

    protected $fillable = [
        'id_suku_cadang',
        'id_admin',
        'jumlah',
        'harga_beli_satuan',
        'total_pengeluaran',
        'stok_sebelum',
        'stok_sesudah',
        'catatan',
    ];

    public function sukuCadang(): BelongsTo
    {
        return $this->belongsTo(SukuCadang::class, 'id_suku_cadang');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin');
    }
}
