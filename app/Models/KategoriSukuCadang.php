<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriSukuCadang extends Model
{
    use HasFactory;

    protected $table = 'kategori_suku_cadang';

    protected $fillable = [
        'nama',
    ];

    /**
     * Relasi ke entitas SukuCadang
     */
    public function sukuCadang()
    {
        return $this->hasMany(SukuCadang::class, 'id_kategori', 'id');
    }
}

