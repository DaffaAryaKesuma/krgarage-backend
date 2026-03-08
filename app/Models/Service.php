<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $table = 'layanan';

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'nama_layanan',
        'deskripsi',
        'harga',
        'durasi_pengerjaan',
        'gambar',
    ];

    /**
     * Casting tipe data.
     */
    protected $casts = [
        'harga'             => 'integer',
        'durasi_pengerjaan' => 'integer',
    ];

    /**
     * Relasi: Layanan terkait dengan banyak pemesanan melalui tabel pivot layanan_pemesanan.
     */
    public function pemesanan(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'layanan_pemesanan', 'id_layanan', 'id_pemesanan')
                    ->withPivot('harga_saat_pesan')
                    ->withTimestamps();
    }
}