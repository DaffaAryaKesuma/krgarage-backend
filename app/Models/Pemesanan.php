<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Model ini mewakili tabel pemesanan, yaitu transaksi servis utama di sistem.
class Pemesanan extends Model
{
    // HasFactory dipakai Laravel untuk membuat data dummy saat testing/seeding.
    use HasFactory;

    // Nama tabel dibuat eksplisit karena tabelnya bukan bentuk plural Laravel default.
    protected $table = 'pemesanan';

    // Konstanta ini mencegah penulisan status yang typo di controller/service.
    public const STATUS_MENUNGGU    = 'Menunggu';
    public const STATUS_DIKONFIRMASI  = 'Dikonfirmasi';
    public const STATUS_DIKERJAKAN = 'Dikerjakan';
    public const STATUS_SELESAI  = 'Selesai';
    public const STATUS_BATAL = 'Batal';

    // Alias bahasa Inggris dipakai agar kode lama/baru tetap bisa memakai arti yang sama.
    public const STATUS_PENDING = self::STATUS_MENUNGGU;
    public const STATUS_CONFIRMED = self::STATUS_DIKONFIRMASI;
    public const STATUS_IN_PROGRESS = self::STATUS_DIKERJAKAN;
    public const STATUS_COMPLETED = self::STATUS_SELESAI;
    public const STATUS_CANCELLED = self::STATUS_BATAL;

    // Konstanta status pembayaran dipakai untuk membedakan lunas dan belum lunas.
    public const PAYMENT_STATUS_UNPAID = 'Belum Lunas';
    public const PAYMENT_STATUS_PAID = 'Lunas';

    // Fillable berisi kolom yang boleh diisi lewat create() atau update().
    protected $fillable = [
        'kode_pemesanan',
        'id_pengguna',
        'id_mekanik',
        'id_vespa',
        'tanggal_pemesanan',
        'jam_pemesanan',
        'status',
        'completed_at',
        'status_pembayaran',
        'paid_at',
        'catatan_pelanggan',
        'catatan_mekanik',
        'total_harga',
    ];

    // Casting membuat completed_at dan paid_at otomatis menjadi object tanggal Carbon.
    protected $casts = [
        'completed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // boot() berjalan saat model aktif, misalnya sebelum data pemesanan baru disimpan.
    protected static function boot()
    {
        parent::boot();

        // creating dijalankan hanya saat INSERT data baru, bukan saat update.
        static::creating(function ($pemesanan) {
            // Jika kode belum dikirim dari controller, sistem membuat kode otomatis.
            if (!$pemesanan->kode_pemesanan) {
                $pemesanan->kode_pemesanan = 'BKG-' . strtoupper(substr(uniqid(), -8));
            }

            // Pemesanan baru otomatis dianggap belum lunas sampai admin menandai lunas.
            if (!$pemesanan->status_pembayaran) {
                $pemesanan->status_pembayaran = self::PAYMENT_STATUS_UNPAID;
            }
        });

    }

    /**
     * Relasi: Pemesanan milik seorang pengguna sebagai pelanggan.
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
     * Relasi: Pemesanan memiliki banyak layanan melalui tabel layanan_pemesanan.
     */
    public function layanan(): BelongsToMany
    {
        // harga_saat_pesan disimpan di pivot agar harga lama tidak berubah saat master layanan berubah.
        return $this->belongsToMany(Layanan::class, 'layanan_pemesanan', 'id_pemesanan', 'id_layanan')
                    ->withTrashed()
                    ->withPivot('nama_layanan_saat_ini', 'harga_saat_pesan')
                    ->withTimestamps();
    }

    /**
     * Relasi: Pemesanan memiliki banyak item suku cadang
     */
    public function itemPemesanan(): HasMany
    {
        return $this->hasMany(ItemPemesanan::class, 'id_pemesanan');
    }

    /**
     * Hitung ulang total harga dari layanan dan suku cadang.
     */
    public function recalculateTotalHarga(): void
    {
        // Ambil harga snapshot di pivot; jika kosong, pakai harga terbaru dari master layanan.
        $totalLayanan = $this->layanan()
            ->get()
            ->sum(function ($layanan) {
                return (float) ($layanan->pivot->harga_saat_pesan ?? $layanan->harga);
            });

        // Total suku cadang dihitung dari harga saat dipakai dikali jumlah.
        $totalSukuCadang = $this->itemPemesanan()
            ->whereNotNull('id_suku_cadang')
            ->get()
            ->sum(function ($item) {
                return $item->harga_saat_ini * $item->jumlah;
            });

        // Simpan hasil hitung ke kolom total_harga agar mudah ditampilkan di laporan.
        $this->total_harga = $totalLayanan + $totalSukuCadang;
        $this->save();
    }

    /**
     * Relasi: Pemesanan terkait dengan banyak suku cadang melalui item_pemesanan
     */
    public function sukuCadang(): BelongsToMany
    {
        // jumlah dan harga_saat_ini berada di tabel pivot item_pemesanan.
        return $this->belongsToMany(SukuCadang::class, 'item_pemesanan', 'id_pemesanan', 'id_suku_cadang')
                    ->withTrashed()
                    ->withPivot('jumlah', 'harga_saat_ini')
                    ->withTimestamps();
    }

    /**
     * Query scope: Filter pemesanan yang sudah selesai
     */
    public function scopeSelesai($query)
    {
        return $query->where('status', self::STATUS_SELESAI);
    }

    /**
     * Query scope: Filter pemesanan yang menunggu konfirmasi
     */
    public function scopeMenunggu($query)
    {
        return $query->where('status', self::STATUS_MENUNGGU);
    }

    /**
     * Query scope: Filter pemesanan yang sudah dikonfirmasi
     */
    public function scopeDikonfirmasi($query)
    {
        return $query->where('status', self::STATUS_DIKONFIRMASI);
    }

    /**
     * Query scope: Filter pemesanan yang sedang diproses
     */
    public function scopeDikerjakan($query)
    {
        return $query->where('status', self::STATUS_DIKERJAKAN);
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
