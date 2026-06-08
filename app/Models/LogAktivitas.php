<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAktivitas extends Model
{
    use HasFactory;

    protected $table = 'log_aktivitas';

    protected $fillable = [
        'id_pengguna',
        'role',
        'aksi',
        'modul',
        'target_tipe',
        'target_id',
        'target_label',
        'deskripsi',
        'data_sebelum',
        'data_sesudah',
    ];

    protected $casts = [
        'data_sebelum' => 'array',
        'data_sesudah' => 'array',
    ];

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
