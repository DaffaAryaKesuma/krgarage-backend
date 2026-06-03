<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\RealtimeEventService;

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

    protected static function booted(): void
    {
        static::created(function (ItemPemesanan $itemPemesanan) {
            RealtimeEventService::publishItemPemesananChanged($itemPemesanan, 'created');
        });

        static::updated(function (ItemPemesanan $itemPemesanan) {
            RealtimeEventService::publishItemPemesananChanged($itemPemesanan, 'updated');
        });

        static::deleted(function (ItemPemesanan $itemPemesanan) {
            RealtimeEventService::publishItemPemesananChanged($itemPemesanan, 'deleted');
        });
    }

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
        return $this->belongsTo(SukuCadang::class, 'id_suku_cadang');
    }
}
