<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model Vespa menyimpan kendaraan pelanggan dan jadwal servis berikutnya.
class Vespa extends Model
{
    // HasFactory dipakai untuk membuat data vespa saat seeding/testing.
    use HasFactory;

    // Nama tabel dibuat eksplisit karena tabel memakai nama singular.
    protected $table = 'vespa';

    /**
     * Atribut yang boleh diisi secara massal lewat create() atau update().
     */
    protected $fillable = [
        'id_pengguna',
        'model',
        'tahun_produksi',
        'plat_nomor',
        'tanggal_servis_terakhir',
        'jeda_hari_servis',
        'tanggal_servis_selanjutnya',
        'reminder_h_minus_3_sent_at',
        'reminder_due_date_sent_at',
        'reminder_h_plus_7_sent_at',
    ];

    // Kolom tanggal otomatis dibaca sebagai object date/Carbon oleh Laravel.
    protected $casts = [
        'tanggal_servis_terakhir'    => 'date',
        'tanggal_servis_selanjutnya' => 'date',
        'reminder_h_minus_3_sent_at'  => 'datetime',
        'reminder_due_date_sent_at'   => 'datetime',
        'reminder_h_plus_7_sent_at'   => 'datetime',
    ];

    // Field tambahan ini ikut muncul saat model Vespa dikirim sebagai JSON.
    protected $appends = ['perlu_servis', 'hari_hingga_servis'];

    /**
     * Mutator: plat nomor selalu disimpan uppercase agar data konsisten.
     */
    public function setPlatNomorAttribute($value): void
    {
        $this->attributes['plat_nomor'] = strtoupper(trim((string) $value));
    }

    /**
     * Relasi: Vespa dimiliki oleh satu pengguna/pelanggan.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Relasi: Satu Vespa bisa punya banyak riwayat pemesanan servis.
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'id_vespa');
    }

    /**
     * Accessor: Menghasilkan field perlu_servis secara otomatis.
     */
    public function getPerluServisAttribute(): bool
    {
        // Kalau belum punya jadwal servis berikutnya, berarti belum bisa dianggap perlu servis.
        if (!$this->tanggal_servis_selanjutnya) {
            return false;
        }

        // Perlu servis jika tanggal hari ini sudah sama/lewat dari jadwal berikutnya.
        return now()->greaterThanOrEqualTo($this->tanggal_servis_selanjutnya);
    }

    /**
     * Accessor: Menghasilkan field hari_hingga_servis secara otomatis.
     */
    public function getHariHinggaServisAttribute(): ?int
    {
        // null berarti sistem belum punya dasar tanggal untuk menghitung.
        if (!$this->tanggal_servis_selanjutnya) {
            return null;
        }

        // false membuat hasil bisa negatif jika jadwal servis sudah lewat.
        return now()->diffInDays($this->tanggal_servis_selanjutnya, false);
    }

    /**
     * Perbarui tanggal servis terakhir ke hari ini dan hitung jadwal berikutnya.
     */
    public function perbaruiTanggalServisTerakhir(): void
    {
        // Default jeda 90 hari dipakai jika pelanggan belum mengatur jeda servis.
        $this->tanggal_servis_terakhir      = now();
        $this->tanggal_servis_selanjutnya   = now()->addDays($this->jeda_hari_servis ?? 90);
        $this->resetPengingatServisEmail();
        $this->save();
    }

    /**
     * Hitung tanggal servis berdasarkan pemesanan terakhir yang statusnya selesai.
     */
    public function hitungTanggalServis(): void
    {
        // Ambil pemesanan selesai paling baru berdasarkan tanggal dan jam servis.
        $pemesananTerakhir = $this->pemesanan()
            ->selesai()
            ->orderBy('tanggal_pemesanan', 'desc')
            ->orderBy('jam_pemesanan', 'desc')
            ->first();

        if ($pemesananTerakhir) {
            // Tanggal servis terakhir mengikuti tanggal pemesanan selesai terbaru.
            $this->tanggal_servis_terakhir    = $pemesananTerakhir->tanggal_pemesanan;
            // Default jeda 30 hari dipakai jika nilai jeda_hari_servis kosong.
            $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
            $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($pemesananTerakhir->tanggal_pemesanan)
                ->addDays($jedaHariServis);
        } else {
            // Jika belum pernah selesai servis, tanggal servis dikosongkan.
            $this->tanggal_servis_terakhir    = null;
            $this->tanggal_servis_selanjutnya = null;
        }
    }

    /**
     * Perbarui tanggal servis memakai data pemesanan tertentu yang sudah selesai.
     */
    public function perbaruiTanggalServisDariPemesanan(Pemesanan $pemesanan): void
    {
        // Dipakai saat admin mengubah status pemesanan menjadi Selesai.
        $this->tanggal_servis_terakhir    = $pemesanan->tanggal_pemesanan;
        $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
        $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($pemesanan->tanggal_pemesanan)
            ->addDays($jedaHariServis);
        $this->resetPengingatServisEmail();
        $this->save();
    }

    /**
     * Ambil informasi servis lengkap dalam bentuk array.
     */
    public function infoServis(): array
    {
        // Pastikan tanggal servis dihitung ulang sebelum datanya dikembalikan.
        $this->hitungTanggalServis();

        return [
            'tanggal_servis_terakhir'    => $this->tanggal_servis_terakhir,
            'tanggal_servis_selanjutnya' => $this->tanggal_servis_selanjutnya,
            'hari_hingga_servis'         => $this->hari_hingga_servis,
            'perlu_servis'               => $this->perlu_servis,
        ];
    }

    /**
     * Query scope: Mengambil tanggal servis terakhir lewat subquery.
     */
    public function scopeDenganTanggalServisTerakhir($query)
    {
        return $query->addSelect([
            // Subquery ini mencegah query berulang saat menampilkan banyak Vespa sekaligus.
            'last_completed_booking_date' => Pemesanan::select('tanggal_pemesanan')
                ->whereColumn('id_vespa', 'vespa.id')
                ->where('status', Pemesanan::STATUS_SELESAI)
                ->orderBy('tanggal_pemesanan', 'desc')
                ->orderBy('jam_pemesanan', 'desc')
                ->limit(1)
        ]);
    }

    /**
     * Sinkronkan tanggal servis dari hasil subquery tanpa query tambahan.
     */
    public function sinkronTanggalServisDariAtribut(): void
    {
        $tanggalServisSelanjutnyaLama = optional($this->tanggal_servis_selanjutnya)->toDateString();

        // Jika subquery menemukan tanggal pemesanan selesai terakhir, pakai tanggal itu.
        if (isset($this->attributes['last_completed_booking_date']) && $this->attributes['last_completed_booking_date']) {
            $this->tanggal_servis_terakhir    = $this->attributes['last_completed_booking_date'];
            $jedaHariServis                   = $this->jeda_hari_servis ?? 30;
            $this->tanggal_servis_selanjutnya = \Carbon\Carbon::parse($this->attributes['last_completed_booking_date'])
                ->addDays($jedaHariServis);
        } else {
            // Jika tidak ada pemesanan selesai, tanggal servis dianggap belum tersedia.
            $this->tanggal_servis_terakhir    = null;
            $this->tanggal_servis_selanjutnya = null;
        }

        $tanggalServisSelanjutnyaBaru = optional($this->tanggal_servis_selanjutnya)->toDateString();
        if ($tanggalServisSelanjutnyaLama !== $tanggalServisSelanjutnyaBaru) {
            $this->resetPengingatServisEmail();
        }
    }

    /**
     * Reset penanda email reminder saat siklus servis berubah.
     */
    public function resetPengingatServisEmail(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'reminder_h_minus_3_sent_at')) {
            return;
        }

        $this->reminder_h_minus_3_sent_at = null;
        $this->reminder_due_date_sent_at = null;
        $this->reminder_h_plus_7_sent_at = null;
    }
}

