<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// Model User mewakili tabel pengguna untuk semua role: pelanggan, admin, mekanik, dan pemilik.
class User extends Authenticatable
{
    // HasApiTokens dipakai Sanctum untuk token login API.
    use HasFactory, Notifiable, HasApiTokens;

    // Nama tabel dibuat eksplisit karena tabel pengguna tidak mengikuti default users.
    protected $table = 'pengguna';

    /**
     * Atribut yang boleh diisi secara massal lewat create() atau update().
     */
    protected $fillable = [
        'nama',
        'email',
        'no_telepon',
        'password',
        'role',
    ];

    /**
     * Atribut yang disembunyikan saat data user dikirim sebagai JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting mengatur perubahan tipe data otomatis dari/ke database.
     */
    protected function casts(): array
    {
        return [
            // Password otomatis di-hash saat disimpan jika belum berbentuk hash.
            'password' => 'hashed',
            // last_seen otomatis dibaca sebagai object tanggal Carbon.
            'last_seen' => 'datetime',
        ];
    }

    /**
     * Relasi: Satu pelanggan bisa memiliki banyak data vespa.
     */
    public function vespas(): HasMany
    {
        return $this->hasMany(Vespa::class, 'id_pengguna');
    }

    /**
     * Relasi: User sebagai pelanggan memiliki banyak pemesanan.
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'id_pengguna');
    }

    /**
     * Relasi: User sebagai mekanik menangani banyak pemesanan.
     */
    public function pemesananDitangani(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'id_mekanik');
    }

    /**
     * Relasi: User menerima banyak notifikasi.
     */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_pengguna');
    }

    /**
     * Query scope: Memudahkan query User::admin().
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Query scope: Memudahkan query User::mekanik().
     */
    public function scopeMekanik($query)
    {
        return $query->where('role', 'mekanik');
    }

    /**
     * Query scope: Memudahkan query User::pelanggan().
     */
    public function scopePelanggan($query)
    {
        // customer ikut diterima untuk kompatibilitas jika ada data lama.
        return $query->whereIn('role', ['pelanggan', 'customer']);
    }
}

