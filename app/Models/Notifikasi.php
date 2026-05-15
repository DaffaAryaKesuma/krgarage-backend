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
    const TIPE_PEMESANAN_DIKONFIRMASI = 'pemesanan_dikonfirmasi';
    const TIPE_PEMESANAN_DIPROSES     = 'pemesanan_diproses';
    const TIPE_PEMESANAN_SELESAI      = 'pemesanan_selesai';
    const TIPE_PEMESANAN_DIBATALKAN   = 'pemesanan_dibatalkan';
    const TIPE_PEMESANAN_DITUGASKAN   = 'pemesanan_ditugaskan';
    const TIPE_PEMESANAN_DIHAPUS      = 'pemesanan_dihapus';
    const TIPE_PEMESANAN_DIPERBARUI   = 'pemesanan_diperbarui';
    const TIPE_STOK_MENIPIS           = 'stok_menipis';
    const TIPE_PEMBAYARAN_DITERIMA    = 'pembayaran_diterima';

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

