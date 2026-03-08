<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'pengguna';

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'nama',
        'email',
        'no_telepon',
        'password',
        'role',
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting atribut.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi: Satu pengguna bisa memiliki banyak vespa.
     */
    public function vespas(): HasMany
    {
        return $this->hasMany(Vespa::class, 'id_pengguna');
    }

    /**
     * Relasi: Pengguna sebagai pelanggan memiliki banyak pemesanan.
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Booking::class, 'id_pengguna');
    }

    /**
     * Relasi: Pengguna sebagai mekanik menangani banyak pemesanan.
     */
    public function pemesananDitangani(): HasMany
    {
        return $this->hasMany(Booking::class, 'id_mekanik');
    }

    /**
     * Relasi: Pengguna memiliki banyak notifikasi.
     */
    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notification::class, 'id_pengguna');
    }

    /**
     * Query scope: Filter pengguna dengan role admin.
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Query scope: Filter pengguna dengan role mekanik.
     */
    public function scopeMekanik($query)
    {
        return $query->where('role', 'mekanik');
    }

    /**
     * Query scope: Filter pengguna dengan role pelanggan.
     */
    public function scopePelanggan($query)
    {
        return $query->where('role', 'customer');
    }
}