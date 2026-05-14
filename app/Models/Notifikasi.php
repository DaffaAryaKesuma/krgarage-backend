<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'id_pengguna',
        'tipe',
        'judul',
        'pesan',
        'sudah_dibaca',
        'id_pemesanan',
    ];

    protected $casts = [
        'sudah_dibaca' => 'boolean',
    ];

    // Konstanta tipe notifikasi
    const TYPE_BOOKING_CONFIRMED   = 'booking_confirmed';
    const TYPE_BOOKING_IN_PROGRESS = 'booking_in_progress';
    const TYPE_BOOKING_COMPLETED   = 'booking_completed';
    const TYPE_BOOKING_CANCELLED   = 'booking_cancelled';
    const TYPE_BOOKING_ASSIGNED    = 'booking_assigned';
    const TYPE_LOW_STOCK           = 'low_stock';
    const TYPE_PAYMENT_RECEIVED    = 'payment_received';

    /**
     * Relasi: Notifikasi milik seorang pengguna.
     */
    public function pengguna()
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Relasi: Notifikasi terkait dengan sebuah pemesanan.
     */
    public function pemesanan()
    {
        return $this->belongsTo(Pemesanan::class, 'id_pemesanan');
    }

    /**
     * Query scope: Filter notifikasi yang belum dibaca.
     */
    public function scopeBelumDibaca($query)
    {
        return $query->where('sudah_dibaca', false);
    }

    /**
     * Tandai notifikasi ini sebagai sudah dibaca.
     */
    public function tandaiDibaca()
    {
        $this->update(['sudah_dibaca' => true]);
    }
}

