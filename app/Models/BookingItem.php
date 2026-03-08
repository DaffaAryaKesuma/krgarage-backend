<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $table = 'item_pemesanan';

    protected $fillable = [
        'id_pemesanan',
        'id_suku_cadang',
        'jumlah',
        'harga_saat_ini',
    ];

    /**
     * Relasi: Item pemesanan milik sebuah pemesanan
     */
    public function pemesanan()
    {
        return $this->belongsTo(Booking::class, 'id_pemesanan');
    }

    /**
     * Relasi: Item pemesanan terkait dengan satu suku cadang
     */
    public function sukuCadang()
    {
        return $this->belongsTo(Sparepart::class, 'id_suku_cadang');
    }
}