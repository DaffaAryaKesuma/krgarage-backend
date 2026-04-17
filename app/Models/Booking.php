<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'pemesanan';

    // Konstanta status yang sudah dibakukan
    public const STATUS_PENDING    = 'Pending';
    public const STATUS_CONFIRMED  = 'Confirmed';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED  = 'Completed';
    public const STATUS_CANCELLED  = 'Cancelled';
    public const PAYMENT_STATUS_UNPAID = 'Belum Lunas';
    public const PAYMENT_STATUS_PAID = 'Lunas';

    protected $fillable = [
        'kode_pemesanan',
        'id_pengguna',
        'id_mekanik',
        'id_vespa',
        'tanggal_pemesanan',
        'jam_pemesanan',
        'status',
        'status_pembayaran',
        'catatan_pelanggan',
        'catatan_mekanik',
        'total_harga',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pemesanan) {
            if (!$pemesanan->kode_pemesanan) {
                $pemesanan->kode_pemesanan = 'BKG-' . strtoupper(substr(uniqid(), -8));
            }

            if (!$pemesanan->status_pembayaran) {
                $pemesanan->status_pembayaran = self::PAYMENT_STATUS_UNPAID;
            }
        });
    }

    /**
     * Relasi: Pemesanan milik seorang pengguna (customer)
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Relasi: Pemesanan ditangani oleh seorang mekanik
     */
    public function mekanik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_mekanik');
    }

    /**
     * Relasi: Pemesanan terkait dengan satu vespa
     */
    public function vespa(): BelongsTo
    {
        return $this->belongsTo(Vespa::class, 'id_vespa');
    }

    /**
     * Relasi: Pemesanan memiliki banyak layanan (many-to-many)
     */
    public function layanan(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'layanan_pemesanan', 'id_pemesanan', 'id_layanan')
                    ->withPivot('harga_saat_pesan')
                    ->withTimestamps();
    }

    /**
     * Relasi: Pemesanan memiliki banyak item suku cadang
     */
    public function itemPemesanan(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'id_pemesanan');
    }

    /**
     * Hitung ulang total harga (layanan + suku cadang)
     */
    public function recalculateTotalHarga(): void
    {
        $totalLayanan = $this->layanan()->sum('harga');

        $totalSukuCadang = $this->itemPemesanan()
            ->whereNotNull('id_suku_cadang')
            ->get()
            ->sum(function ($item) {
                return $item->harga_saat_ini * $item->jumlah;
            });

        $this->total_harga = $totalLayanan + $totalSukuCadang;
        $this->save();
    }

    /**
     * Relasi: Pemesanan terkait dengan banyak suku cadang melalui item_pemesanan
     */
    public function sukuCadang(): BelongsToMany
    {
        return $this->belongsToMany(Sparepart::class, 'item_pemesanan', 'id_pemesanan', 'id_suku_cadang')
                    ->withPivot('jumlah', 'harga_saat_ini')
                    ->withTimestamps();
    }

    /**
     * Query scope: Filter pemesanan yang sudah selesai
     */
    public function scopeSelesai($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Query scope: Filter pemesanan yang menunggu konfirmasi
     */
    public function scopeMenunggu($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Query scope: Filter pemesanan yang sudah dikonfirmasi
     */
    public function scopeDikonfirmasi($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Query scope: Filter pemesanan yang sedang diproses
     */
    public function scopeDiproses($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Query scope: Filter pemesanan yang sudah dibayar lunas
     */
    public function scopeSudahDibayar($query)
    {
        return $query->where('status_pembayaran', self::PAYMENT_STATUS_PAID);
    }

    /**
     * Query scope: Filter pemesanan yang belum dibayar lunas
     */
    public function scopeBelumDibayar($query)
    {
        return $query->where('status_pembayaran', self::PAYMENT_STATUS_UNPAID);
    }
}