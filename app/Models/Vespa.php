<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vespa extends Model
{
    use HasFactory;

    protected $table = 'vespa';

    /**
     * Atribut yang boleh diisi secara massal.
     */
    protected $fillable = [
        'id_pengguna',
        'model',
        'tahun_produksi',
        'plat_nomor',
        'tanggal_servis_terakhir',
        'jeda_hari_servis',
        'tanggal_servis_selanjutnya',
    ];

    protected $casts = [
        'tanggal_servis_terakhir'    => 'date',
        'tanggal_servis_selanjutnya' => 'date',
    ];

    protected $appends = ['perlu_servis', 'hari_hingga_servis'];

    /**
     * Relasi: Vespa milik seorang pengguna.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Relasi: Vespa memiliki banyak pemesanan.
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'id_vespa');
    }

    /**
     * Accessor: Cek apakah vespa perlu diservis.
     */
    public function getPerluServisAttribute(): bool
    {
        if (!$this->tanggal_servis_selanjutnya) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->tanggal_servis_selanjutnya);
    }

    /**
     * Accessor: Hitung berapa hari lagi sampai jadwal servis.
     */
    public function getHariHinggaServisAttribute(): ?int
    {
        if (!$this->tanggal_servis_selanjutnya) {
            return null;
        }

        return now()->diffInDays($this->tanggal_servis_selanjutnya, false);
    }

    /**
     * Perbarui tanggal servis terakhir dan hitung jadwal servis berikutnya.
     */
    public function perbaruiTanggalServisTerakhir(): void
    {
        $this->tanggal_servis_terakhir      = now();
        $this->tanggal_servis_selanjutnya   = now()->addDays($this->jeda_hari_servis ?? 90);
        $this->save();
    }

    /**
     * Hitung dan perbarui tanggal servis berdasarkan pemesanan terakhir yang selesai.
     */
    public function hitungTanggalServis(): void
    {
        $pemesananTerakhir = $this->pemesanan()
            ->selesai()
            ->orderBy('tanggal_pemesanan', 'desc')
            ->orderBy('jam_pemesanan', 'desc')
            ->first();

        if ($pemesananTerakhir) {
            $this->tanggal_servis_terakhir    = $pemesananTerakhir->tanggal_pemesanan;
            $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
            $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($pemesananTerakhir->tanggal_pemesanan)
                ->addDays($jedaHariServis);
        } else {
            $this->tanggal_servis_terakhir    = null;
            $this->tanggal_servis_selanjutnya = null;
        }
    }

    /**
     * Perbarui tanggal servis setelah pemesanan selesai.
     */
    public function perbaruiTanggalServisDariPemesanan(Pemesanan $pemesanan): void
    {
        $this->tanggal_servis_terakhir    = $pemesanan->tanggal_pemesanan;
        $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
        $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($pemesanan->tanggal_pemesanan)
            ->addDays($jedaHariServis);
        $this->save();
    }

    /**
     * Ambil informasi lengkap servis vespa.
     */
    public function infoServis(): array
    {
        $this->hitungTanggalServis();

        return [
            'tanggal_servis_terakhir'    => $this->tanggal_servis_terakhir,
            'tanggal_servis_selanjutnya' => $this->tanggal_servis_selanjutnya,
            'hari_hingga_servis'         => $this->hari_hingga_servis,
            'perlu_servis'               => $this->perlu_servis,
        ];
    }

    /**
     * Query scope: Eager load dengan tanggal servis terakhir (mencegah N+1).
     */
    public function scopeDenganTanggalServisTerakhir($query)
    {
        return $query->addSelect([
            'last_completed_booking_date' => Pemesanan::select('tanggal_pemesanan')
                ->whereColumn('id_vespa', 'vespa.id')
                ->where('status', Pemesanan::STATUS_SELESAI)
                ->orderBy('tanggal_pemesanan', 'desc')
                ->orderBy('jam_pemesanan', 'desc')
                ->limit(1)
        ]);
    }

    /**
     * Sinkronkan tanggal servis dari atribut subquery (tanpa query tambahan).
     */
    public function sinkronTanggalServisDariAtribut(): void
    {
        if (isset($this->attributes['last_completed_booking_date']) && $this->attributes['last_completed_booking_date']) {
            $this->tanggal_servis_terakhir    = $this->attributes['last_completed_booking_date'];
            $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
            $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($this->attributes['last_completed_booking_date'])
                ->addDays($jedaHariServis);
        } else {
            $this->tanggal_servis_terakhir    = null;
            $this->tanggal_servis_selanjutnya = null;
        }
    }
}

