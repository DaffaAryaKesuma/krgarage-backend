<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RiwayatStokSukuCadang extends Model
{
    use HasFactory;

    protected $table = 'riwayat_stok_suku_cadang';

    protected $appends = [
        'foto_struk_url',
        'foto_struk_tersedia',
    ];

    protected $fillable = [
        'id_suku_cadang',
        'id_admin',
        'jumlah',
        'harga_beli_satuan',
        'total_pengeluaran',
        'stok_sebelum',
        'stok_sesudah',
        'catatan',
        'foto_struk',
    ];

    public function getFotoStrukUrlAttribute(): ?string
    {
        if (!$this->foto_struk || !Storage::disk('public')->exists($this->foto_struk)) {
            return null;
        }

        return url('/api/storage/'.ltrim($this->foto_struk, '/'));
    }

    public function getFotoStrukTersediaAttribute(): bool
    {
        return $this->foto_struk
            ? Storage::disk('public')->exists($this->foto_struk)
            : false;
    }

    public function sukuCadang(): BelongsTo
    {
        return $this->belongsTo(SukuCadang::class, 'id_suku_cadang');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin');
    }
}
